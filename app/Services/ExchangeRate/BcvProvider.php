<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate;

use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight BCV rates provider using HTML scraping and XPath.
 * Returns array: ['valid_from' => iso8601, 'USD' => float, 'EUR' => float]
 */
class BcvProvider
{
    /**
     * @return array{valid_from:string, USD?:float, EUR?:float}
     */
    public function fetchRates(): array
    {
        $cfg = (array) config('services.bcv', []);
        $url = (string) ($cfg['url'] ?? 'https://www.bcv.org.ve/');
        $timeout = (int) ($cfg['timeout'] ?? 15);
        $verify = (bool) ($cfg['verify'] ?? false);
        $attempts = (int) ($cfg['retry_attempts'] ?? 3);
        $delay = (int) ($cfg['retry_delay'] ?? 60);

        $lastEx = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $resp = Http::withOptions(['verify' => $verify])->timeout($timeout)->get($url);
                $html = $resp->body();
                if ($resp->failed() || ! $html) {
                    throw new Exception('HTTP status '.$resp->status());
                }

                $doc = new DOMDocument;
                // Suppress warnings from malformed HTML
                @$doc->loadHTML($html);
                $xp = new DOMXPath($doc);

                // Try to extract ISO date from elements with class 'date-display-single'
                $validFromIso = null;
                $dateNodes = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' date-display-single ')]");
                if ($dateNodes && $dateNodes->length > 0) {
                    $node = $dateNodes->item(0);
                    $contentAttr = $node?->attributes?->getNamedItem('content')?->nodeValue;
                    if ($contentAttr) {
                        $validFromIso = (new Carbon($contentAttr))->toIso8601String();
                    } else {
                        $text = '';
                        if ($node instanceof \DOMNode) {
                            $text = (string) $node->textContent;
                        }
                        $txt = trim($text) ?: null;
                        if ($txt) {
                            // Best-effort parse; fall back to now if fails
                            try {
                                $validFromIso = Carbon::parse($txt)->toIso8601String();
                            } catch (\Throwable $e) {
                                $validFromIso = null;
                            }
                        }
                    }
                }
                if (! $validFromIso) {
                    $validFromIso = Carbon::now((string) ($cfg['timezone'] ?? 'America/Caracas'))->toIso8601String();
                }

                $rates = ['valid_from' => $validFromIso];

                // USD strong inside #dolar
                $usdNodes = $xp->query("//*[@id='dolar']//strong");
                if ($usdNodes && $usdNodes->length > 0) {
                    $n = $usdNodes->item(0);
                    $rates['USD'] = $this->normalizeRate((string) ($n instanceof \DOMNode ? $n->textContent : ''));
                }
                // EUR strong inside #euro
                $eurNodes = $xp->query("//*[@id='euro']//strong");
                if ($eurNodes && $eurNodes->length > 0) {
                    $n = $eurNodes->item(0);
                    $rates['EUR'] = $this->normalizeRate((string) ($n instanceof \DOMNode ? $n->textContent : ''));
                }

                if (! isset($rates['USD']) && ! isset($rates['EUR'])) {
                    throw new Exception('BCV parsing produced no rates');
                }

                Log::info('BCV rates fetched', $rates);

                return $rates;
            } catch (\Throwable $e) {
                $lastEx = $e;
                Log::warning('BCV fetch attempt failed: '.$e->getMessage());
                if ($i < $attempts - 1) {
                    sleep($delay);
                }
            }
        }

        throw new Exception('BCV fetch failed after attempts', 0, $lastEx);
    }

    private function normalizeRate(string $text): float
    {
        $t = trim($text);
        // Replace thousands and decimal separators (e.g., 35.123,45 -> 35123.45)
        $t = str_replace(['.', ','], ['', '.'], $t);
        $val = (float) $t;

        return $val > 0 ? $val : 0.0;
    }
}
