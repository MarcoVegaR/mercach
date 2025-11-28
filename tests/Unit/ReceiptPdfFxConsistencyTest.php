<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReceiptPdfGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifica que los helpers FX de ReceiptPdfGenerator siguen la misma
 * política de truncamiento canónica usada en el resto del sistema.
 */
class ReceiptPdfFxConsistencyTest extends TestCase
{
    /**
     * Política canónica toVes (ccy -> Bs) basada en FxConversionHelper::toVes.
     */
    private function canonicalToVes(int $amountMinor, float $rate): int
    {
        if ($amountMinor <= 0 || $rate <= 0) {
            return 0;
        }

        $rateMinor = (int) round($rate * 100);
        $prod = $amountMinor * $rateMinor;

        return (int) intdiv($prod, 100);
    }

    /**
     * Política canónica fromVes (Bs -> ccy) basada en FxConversionHelper::fromVes.
     */
    private function canonicalFromVes(int $bsMinor, float $rate): int
    {
        if ($bsMinor <= 0 || $rate <= 0) {
            return 0;
        }

        $prod = (int) round(($bsMinor * 100) / $rate);

        return (int) intdiv($prod, 100);
    }

    private function invokeFxMinorFromVesToCcy(int $vesMinor, float $rate): int
    {
        $svc = new ReceiptPdfGenerator;
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('fxMinorFromVesToCcy');
        $m->setAccessible(true);

        return (int) $m->invoke($svc, $vesMinor, $rate);
    }

    private function invokeFxMinorFromCcyToVes(int $ccyMinor, float $rate): int
    {
        $svc = new ReceiptPdfGenerator;
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('fxMinorFromCcyToVes');
        $m->setAccessible(true);

        return (int) $m->invoke($svc, $ccyMinor, $rate);
    }

    public function test_fx_minor_from_ves_to_ccy_matches_canonical_from_ves(): void
    {
        $cases = [
            ['bs' => 502500, 'rate' => 50.25],   // 5025.00 Bs -> €100.00
            ['bs' => 500000, 'rate' => 50.25],   // 5000.00 Bs -> ~€99.50 (truncado)
            ['bs' => 28350,  'rate' => 283.50],  // 283.50 Bs -> €1.00
            ['bs' => 99999,  'rate' => 50.25],
            ['bs' => 5025,   'rate' => 50.25],   // 50.25 Bs -> €1.00
        ];

        foreach ($cases as $c) {
            $expected = $this->canonicalFromVes($c['bs'], $c['rate']);
            $actual = $this->invokeFxMinorFromVesToCcy($c['bs'], $c['rate']);

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    'fxMinorFromVesToCcy(%d, %.4f) debería ser %d, se obtuvo %d',
                    $c['bs'],
                    $c['rate'],
                    $expected,
                    $actual,
                ),
            );
        }
    }

    public function test_fx_minor_from_ccy_to_ves_matches_canonical_to_ves(): void
    {
        $cases = [
            ['ccy' => 10000, 'rate' => 50.25],   // €100.00 -> 5025.00 Bs
            ['ccy' => 32519, 'rate' => 50.25],   // €325.19 -> 16340.79 Bs (truncado)
            ['ccy' => 100,   'rate' => 283.50],  // €1.00 -> 283.50 Bs
            ['ccy' => 99999, 'rate' => 1.00],
            ['ccy' => 1,     'rate' => 50.25],
        ];

        foreach ($cases as $c) {
            $expected = $this->canonicalToVes($c['ccy'], $c['rate']);
            $actual = $this->invokeFxMinorFromCcyToVes($c['ccy'], $c['rate']);

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    'fxMinorFromCcyToVes(%d, %.4f) debería ser %d, se obtuvo %d',
                    $c['ccy'],
                    $c['rate'],
                    $expected,
                    $actual,
                ),
            );
        }
    }
}
