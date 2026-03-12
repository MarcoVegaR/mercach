<?php

declare(strict_types=1);

namespace App\Exports;

use App\Contracts\Exports\ExporterInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * XlsxExporter - Exportador de datos en formato XLSX compatible con Excel
 * Genera un archivo OOXML real (.xlsx) que Excel y LibreOffice pueden abrir sin advertencias
 */
class XlsxExporter implements ExporterInterface
{
    /**
     * Exporta datos en formato XLSX compatible con Excel
     *
     * @param  iterable<array<string, mixed>>  $rows  Iterador de filas de datos
     * @param  array<string>  $columns  Columnas a incluir en la exportación
     */
    public function stream(iterable $rows, array $columns): StreamedResponse
    {
        $filename = 'export_'.date('Y-m-d_His').'.xlsx';
        $response = new StreamedResponse(function () use ($rows, $columns) {
            $sheetHandle = null;
            $tempDir = $this->makeTempDir();
            $sheetPath = $tempDir.'/sheet1.xml';
            $xlsxPath = $tempDir.'/export.xlsx';

            try {
                $columnKeys = array_is_list($columns) ? $columns : array_keys($columns);
                $headerLabels = array_is_list($columns) ? $columns : array_values($columns);

                $sheetHandle = fopen($sheetPath, 'wb');
                if ($sheetHandle === false) {
                    throw new RuntimeException('Unable to open temporary worksheet file.');
                }

                fwrite($sheetHandle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
                fwrite($sheetHandle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');

                $rowNumber = 1;
                $this->writeSheetRow($sheetHandle, $rowNumber++, $headerLabels);

                foreach ($rows as $row) {
                    $xlsxRow = [];
                    foreach ($columnKeys as $key) {
                        $value = $row[$key] ?? '';
                        if (is_bool($value)) {
                            $value = $value ? 'Activo' : 'Inactivo';
                        }
                        $xlsxRow[] = $value;
                    }
                    $this->writeSheetRow($sheetHandle, $rowNumber++, $xlsxRow);
                }

                fwrite($sheetHandle, '</sheetData></worksheet>');
                fclose($sheetHandle);

                $zip = new ZipArchive;
                if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Unable to create XLSX archive.');
                }

                $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
                $zip->addFromString('_rels/.rels', $this->rootRelsXml());
                $zip->addFromString('docProps/app.xml', $this->appPropsXml());
                $zip->addFromString('docProps/core.xml', $this->corePropsXml());
                $zip->addFromString('xl/workbook.xml', $this->workbookXml());
                $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
                $zip->addFromString('xl/styles.xml', $this->stylesXml());
                $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
                $zip->close();

                $outputHandle = fopen('php://output', 'wb');
                $inputHandle = fopen($xlsxPath, 'rb');

                if ($outputHandle === false || $inputHandle === false) {
                    throw new RuntimeException('Unable to stream XLSX file.');
                }

                stream_copy_to_stream($inputHandle, $outputHandle);

                fclose($inputHandle);
                fclose($outputHandle);
            } finally {
                if (isset($sheetHandle) && is_resource($sheetHandle)) {
                    fclose($sheetHandle);
                }
                $this->removeTempDir($tempDir);
            }
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $values
     */
    private function writeSheetRow($handle, int $rowNumber, array $values): void
    {
        fwrite($handle, '<row r="'.$rowNumber.'">');

        foreach ($values as $columnIndex => $value) {
            $cellReference = $this->columnName($columnIndex + 1).$rowNumber;
            fwrite($handle, '<c r="'.$cellReference.'" t="inlineStr"><is><t>'.$this->escapeXml($this->normalizeValue($value)).'</t></is></c>');
        }

        fwrite($handle, '</row>');
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function makeTempDir(): string
    {
        $basePath = sys_get_temp_dir().'/mercach_xlsx_'.bin2hex(random_bytes(8));

        if (! mkdir($basePath, 0777, true) && ! is_dir($basePath)) {
            throw new RuntimeException('Unable to create temporary export directory.');
        }

        return $basePath;
    }

    private function removeTempDir(?string $path): void
    {
        if (! $path || ! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.'/'.$item;
            if (is_file($itemPath)) {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function rootRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Export" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
    }

    private function workbookRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1">
        <font>
            <sz val="11"/>
            <name val="Calibri"/>
        </font>
    </fonts>
    <fills count="2">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
    </fills>
    <borders count="1">
        <border>
            <left/><right/><top/><bottom/><diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>
XML;
    }

    private function appPropsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>Mercach</Application>
</Properties>
XML;
    }

    private function corePropsXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>Mercach</dc:creator>
    <cp:lastModifiedBy>Mercach</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
</cp:coreProperties>
XML;
    }
}
