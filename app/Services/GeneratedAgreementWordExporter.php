<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainActionException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Tab;
use RuntimeException;
use ZipArchive;

class GeneratedAgreementWordExporter
{
    public const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private string $css = '';

    public function render(string $html): string
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new DOMXPath($document);
        $this->css = $document->getElementsByTagName('style')->item(0)->textContent ?? '';
        $word = new PhpWord;
        $word->setDefaultFontName('DejaVu Sans');
        $word->setDefaultFontSize(10);
        $spaceAfter = $this->points('p', 'margin-bottom', 0);
        if (preg_match('/(?:^|})\s*p\s*\{[^}]*\bmargin:\s*0\s+0\s+([\d.]+)mm/', $this->css, $margin)) {
            $spaceAfter = Converter::cmToPoint((float) $margin[1] / 10);
        }
        $word->setDefaultParagraphStyle([
            'alignment' => 'both', 'spaceBefore' => 0, 'spaceAfter' => (int) round(Converter::pointToTwip($spaceAfter)),
            'spacing' => 423, 'spacingLineRule' => 'exact', 'widowControl' => false,
        ]);
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('es-VE'));
        $section = $word->addSection([
            'pageSizeW' => (int) round(Converter::cmToTwip(21.6)), 'pageSizeH' => (int) round(Converter::cmToTwip(33)),
            'marginTop' => Converter::cmToTwip(3), 'marginBottom' => Converter::cmToTwip(2.4),
            'marginLeft' => Converter::cmToTwip(2.2), 'marginRight' => Converter::cmToTwip(2.2),
            'headerHeight' => 0, 'footerHeight' => 0,
        ]);
        $this->addBackground($section, $xpath);
        foreach ($xpath->query('/html/body/*') as $node) {
            if (! $node instanceof DOMElement || str_contains($node->getAttribute('class'), 'letterhead')) {
                continue;
            }
            if ($node->getAttribute('class') === 'header') {
                $this->addHeading($section, $node);
            } elseif ($node->tagName === 'table') {
                $this->addTable($section, $node);
            } elseif (in_array($node->tagName, ['p', 'h1', 'div'], true)) {
                $style = [];
                $font = [];
                if ($node->tagName === 'h1') {
                    $style = ['alignment' => 'center', 'spaceBefore' => Converter::cmToTwip(0.15), 'spaceAfter' => Converter::cmToTwip(0.9), 'keepNext' => true];
                    $font = ['bold' => true, 'size' => 12];
                } elseif ($node->getAttribute('class') === 'number') {
                    $style = ['alignment' => 'center', 'spaceAfter' => Converter::cmToTwip(0.5), 'keepNext' => true];
                    $font = ['size' => 9];
                } elseif (str_starts_with(trim($node->textContent), 'Finalmente se declara')) {
                    $style = ['keepLines' => true, 'keepNext' => true];
                }
                $run = $section->addTextRun($style + [
                    'tabs' => [new Tab('right', (int) round(Converter::cmToTwip(17.2)), 'hyphen')],
                ]);
                $this->addInline($run, $node, $font);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'agreement-word-');
        if ($path === false) {
            throw new RuntimeException('No se pudo preparar la descarga Word.');
        }
        $escaping = Settings::isOutputEscapingEnabled();
        try {
            Settings::setOutputEscapingEnabled(true);
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('No se pudo generar el documento Word.');
            }
            $xml = $zip->getFromName('word/document.xml');
            $zip->addFromString('word/document.xml', str_replace(
                '<w:t xml:space="preserve">'."\t".'</w:t>', '<w:tab/>', (string) $xml,
            ));
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            unlink($path);
        }
    }

    private function addHeading(Section $section, DOMElement $node): void
    {
        $anchor = $section->addTextRun(['spacing' => 1, 'spacingLineRule' => 'exact', 'spaceAfter' => 0]);
        $table = $section->addTable(['borderStyle' => 'none', 'cellMargin' => 0, 'layout' => 'fixed']);
        $table->addRow((int) round(Converter::pointToTwip($this->points('.header', 'min-height', 124.72))), ['exactHeight' => true]);
        $cell = $table->addCell((int) round(Converter::cmToTwip(4.8)), [
            'valign' => 'top', 'noWrap' => false, 'paddingTop' => (int) round(Converter::pointToTwip($this->points('.legal', 'padding-top', 0))),
        ]);
        $legal = (new DOMXPath($node->ownerDocument))->query('.//div[@class="legal"]', $node)->item(0);
        if ($legal !== null) {
            $this->addInline($cell->addTextRun(['alignment' => 'center', 'spacing' => 300, 'spacingLineRule' => 'exact']), $legal, ['size' => 9]);
        }
        $table->addCell((int) round(Converter::cmToTwip(12.4)));
        foreach ($node->getElementsByTagName('img') as $image) {
            $bytes = $this->imageBytes($image);
            $size = getimagesizefromstring($bytes);
            $height = $this->points('.logo', 'height', 119.06);
            $width = $height * $size[0] / $size[1];
            $anchor->addImage($bytes, [
                'width' => $width, 'height' => $height,
                'positioning' => 'absolute', 'wrappingStyle' => 'infront',
                'posHorizontal' => 'absolute', 'posVertical' => 'absolute',
                'posHorizontalRel' => 'page', 'posVerticalRel' => 'page',
                'marginLeft' => Converter::cmToPoint(19.4) - $width,
                'marginTop' => Converter::cmToPoint(3) + $this->points('.logo', 'top', 0),
            ]);
        }
    }

    private function addTable(Section $section, DOMElement $node): void
    {
        $signatures = $node->getAttribute('class') === 'signatures';
        $section->addTextBreak(1, ['size' => 1], ['spacing' => 227, 'spacingLineRule' => 'exact', 'spaceAfter' => 0, 'keepNext' => true]);
        $table = $section->addTable([
            'borderSize' => $signatures ? null : 4, 'borderColor' => '666666',
            'borderStyle' => $signatures ? 'none' : 'single',
            'cellMargin' => $signatures ? 0 : 45, 'layout' => 'fixed',
            'width' => Converter::cmToTwip(17.2), 'unit' => 'dxa',
        ]);
        foreach ($node->getElementsByTagName('tr') as $row) {
            $cells = array_filter(iterator_to_array($row->childNodes), fn (DOMNode $child): bool => $child instanceof DOMElement && in_array($child->tagName, ['th', 'td'], true));
            $table->addRow(null, ['cantSplit' => true, 'tblHeader' => $row->parentNode->nodeName === 'thead']);
            foreach (array_values($cells) as $index => $cellNode) {
                $fraction = ! $signatures && count($cells) === 5 ? [0.21, 0.18, 0.19, 0.24, 0.18][$index] : 1 / count($cells);
                $cell = $table->addCell((int) round(Converter::cmToTwip(17.2) * $fraction), [
                    'valign' => 'top', 'noWrap' => false,
                    'paddingTop' => $signatures ? (int) round(Converter::cmToTwip(1.8)) : 45,
                    'bgColor' => $cellNode->tagName === 'th' ? 'EEEEEE' : null,
                ]);
                $this->addInline($cell->addTextRun([
                    'alignment' => $signatures ? 'center' : ($index === 0 ? 'left' : 'right'),
                    'spacing' => $signatures ? 423 : 338, 'spacingLineRule' => 'exact', 'spaceAfter' => 0,
                ]), $cellNode, ['size' => $signatures ? 10 : ($cellNode->tagName === 'th' ? 7 : 8), 'bold' => $cellNode->tagName === 'th']);
            }
        }
        if (! $signatures) {
            $section->addTextBreak(1, ['size' => 1], ['spacing' => 227, 'spacingLineRule' => 'exact', 'spaceAfter' => 0]);
        }
    }

    /** @param array<string, mixed> $font */
    private function addInline(TextRun $run, DOMNode $node, array $font = []): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $child->textContent);
                if ($text !== '') {
                    $run->addText($text, $font);
                }
            } elseif ($child instanceof DOMElement) {
                if ($child->tagName === 'br') {
                    $run->addTextBreak();
                } else {
                    $style = $font;
                    if (in_array($child->tagName, ['strong', 'b'], true)) {
                        $style['bold'] = true;
                    }
                    if (in_array($child->tagName, ['em', 'i'], true)) {
                        $style['italic'] = true;
                    }
                    $this->addInline($run, $child, $style);
                    if ($child->getAttribute('class') === 'clause-end' && $child->textContent === '.') {
                        $run->addText("\t");
                    }
                }
            }
        }
    }

    private function addBackground(Section $section, DOMXPath $xpath): void
    {
        $node = $xpath->query('//div[contains(@class, "letterhead")]/img')->item(0);
        if (! $node instanceof DOMElement) {
            return;
        }
        $source = imagecreatefromstring($this->imageBytes($node));
        $width = imagesx($source);
        $height = (int) round($width * 330 / 216);
        $background = imagecreatetruecolor($width, $height);
        imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
        imagecopyresampled($background, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        if ($xpath->query('//div[contains(@class, "letterhead-top")]')->length > 0) {
            $scale = $this->points('.letterhead-top img', 'width', Converter::cmToPoint(21.6)) / Converter::cmToPoint(21.6);
            $half = (int) floor($height / 2);
            imagefilledrectangle($background, 0, 0, $width - 1, $half - 1, imagecolorallocate($background, 255, 255, 255));
            imagesetclip($background, 0, 0, $width - 1, $half - 1);
            imagecopyresampled($background, $source, 0, 0, 0, 0, (int) round($width * $scale), (int) round($height * $scale), imagesx($source), imagesy($source));
        }
        ob_start();
        imagepng($background);
        $png = (string) ob_get_clean();
        $section->addHeader()->addWatermark($png, [
            'width' => Converter::cmToPoint(21.6), 'height' => Converter::cmToPoint(33),
            'wrappingStyle' => 'behind', 'posHorizontalRel' => 'page', 'posVerticalRel' => 'page',
            'marginLeft' => 0, 'marginTop' => 0,
        ]);
    }

    private function imageBytes(DOMElement $image): string
    {
        if (! preg_match('~^data:image/(?:png|jpeg|gif);base64,([A-Za-z0-9+/=\s]+)$~', $image->getAttribute('src'), $match)) {
            throw new DomainActionException('La imagen conservada no es compatible con la exportación Word. Utiliza la descarga PDF.');
        }
        $bytes = base64_decode($match[1], true);
        if ($bytes === false || @getimagesizefromstring($bytes) === false) {
            throw new DomainActionException('La imagen conservada no es válida. Utiliza la descarga PDF.');
        }

        return $bytes;
    }

    private function points(string $selector, string $property, float $default): float
    {
        if (preg_match('/(?:^|})\s*'.preg_quote($selector, '/').'\s*\{([^}]+)\}/', $this->css, $rule)
            && preg_match('/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*(-?[\d.]+)(mm|pt)/', $rule[1], $value)) {
            return $value[2] === 'mm' ? Converter::cmToPoint((float) $value[1] / 10) : (float) $value[1];
        }

        return $default;
    }
}
