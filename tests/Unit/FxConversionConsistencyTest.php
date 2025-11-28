<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Services\FxRateServiceInterface;
use App\Services\DashboardService;
use App\Services\DebtAnalysisService;
use App\Services\EconomicProfileService;
use App\Support\FxConversionHelper;
use Illuminate\Contracts\Container\Container;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests para garantizar consistencia en conversiones FX entre todos los servicios.
 *
 * CRÍTICO: Estos tests verifican que todos los servicios usan la misma política
 * de truncamiento para conversiones FX, evitando discrepancias en reportes financieros.
 */
class FxConversionConsistencyTest extends TestCase
{
    private FxRateServiceInterface $fxServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock del FxRateService que devuelve tasas predefinidas
        $this->fxServiceMock = Mockery::mock(FxRateServiceInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test que verifica la política de truncamiento toVes (EUR/USD → Bs).
     *
     * La política correcta es:
     * - amount (2dp) * rate (2dp) => 4dp
     * - Truncar a 2dp usando intdiv
     *
     * Ejemplo: €100.00 * 50.25 = 5025.00 Bs (NO 5025.01 por redondeo)
     */
    public function test_to_ves_truncation_policy(): void
    {
        // Caso 1: Multiplicación exacta
        // €100.00 (10000 minor) * 50.25 (rate) = 5025.00 Bs (502500 minor)
        $this->assertEquals(502500, $this->toVesMinor(10000, 50.25));

        // Caso 2: Resultado con más de 2 decimales que debe truncarse
        // €100.00 (10000 minor) * 50.333 (rate) = 5033.30 Bs (truncado, no 5033.33)
        // 10000 * 5033 = 50330000 / 100 = 503300
        $this->assertEquals(503300, $this->toVesMinor(10000, 50.33));

        // Caso 3: Monto pequeño
        // €1.00 (100 minor) * 50.25 = 50.25 Bs (5025 minor)
        $this->assertEquals(5025, $this->toVesMinor(100, 50.25));

        // Caso 4: Monto con centavos
        // €325.19 (32519 minor) * 50.25 = 16340.79 Bs (1634079 minor, truncado)
        // 32519 * 5025 = 163407975 / 100 = 1634079
        $this->assertEquals(1634079, $this->toVesMinor(32519, 50.25));

        // Caso 5: Edge case - monto cero
        $this->assertEquals(0, $this->toVesMinor(0, 50.25));

        // Caso 6: Edge case - tasa cero
        $this->assertEquals(0, $this->toVesMinor(10000, 0.0));
    }

    /**
     * Test que verifica la política de truncamiento fromVes (Bs → EUR/USD).
     *
     * La política correcta es:
     * - Bs (2dp) / rate (2dp) => 4dp
     * - Truncar a 2dp usando intdiv
     */
    public function test_from_ves_truncation_policy(): void
    {
        // Caso 1: División exacta
        // 5025.00 Bs (502500 minor) / 50.25 = €100.00 (10000 minor)
        $this->assertEquals(10000, $this->fromVesMinor(502500, 50.25));

        // Caso 2: División con residuo que debe truncarse
        // 5000.00 Bs (500000 minor) / 50.25 = €99.50 (9950 minor, truncado)
        // (500000 * 100) / 50.25 = 995024.87... => round => 995025 / 100 = 9950
        $this->assertEquals(9950, $this->fromVesMinor(500000, 50.25));

        // Caso 3: Monto pequeño
        // 50.25 Bs (5025 minor) / 50.25 = €1.00 (100 minor)
        $this->assertEquals(100, $this->fromVesMinor(5025, 50.25));

        // Caso 4: Edge case - monto cero
        $this->assertEquals(0, $this->fromVesMinor(0, 50.25));

        // Caso 5: Edge case - tasa cero
        $this->assertEquals(0, $this->fromVesMinor(502500, 0.0));
    }

    /**
     * Test que verifica que todos los servicios producen el mismo resultado para toVes.
     */
    public function test_all_services_produce_same_to_ves_result(): void
    {
        $testCases = [
            ['amount' => 10000, 'rate' => 50.25],
            ['amount' => 32519, 'rate' => 50.25],
            ['amount' => 100, 'rate' => 283.50],
            ['amount' => 99999, 'rate' => 1.0],
            ['amount' => 1, 'rate' => 50.25],
        ];

        foreach ($testCases as $case) {
            $amount = $case['amount'];
            $rate = $case['rate'];

            // Resultado esperado usando la política canónica
            $expected = $this->toVesMinor($amount, $rate);

            // Verificar que EconomicProfileService produce el mismo resultado
            $economicResult = $this->invokeToVesMinorOn(
                EconomicProfileService::class,
                $amount,
                $rate
            );

            $this->assertEquals(
                $expected,
                $economicResult,
                "EconomicProfileService::toVesMinor({$amount}, {$rate}) debería ser {$expected}, pero fue {$economicResult}"
            );

            // Verificar que DashboardService produce el mismo resultado
            $dashboardResult = $this->invokeDashboardToVesMinor($amount, $rate);

            $this->assertEquals(
                $expected,
                $dashboardResult,
                "DashboardService::toVesMinor({$amount}, {$rate}) debería ser {$expected}, pero fue {$dashboardResult}"
            );

            // Verificar que DebtAnalysisService produce el mismo resultado
            $debtResult = $this->invokeDebtToVesMinor($amount, $rate);

            $this->assertEquals(
                $expected,
                $debtResult,
                "DebtAnalysisService::toVesMinor({$amount}, {$rate}) debería ser {$expected}, pero fue {$debtResult}"
            );
        }
    }

    /**
     * Test que verifica que todos los servicios producen el mismo resultado para fromVes.
     */
    public function test_all_services_produce_same_from_ves_result(): void
    {
        $testCases = [
            ['bs' => 502500, 'rate' => 50.25],
            ['bs' => 500000, 'rate' => 50.25],
            ['bs' => 28350, 'rate' => 283.50],
            ['bs' => 99999, 'rate' => 1.0],
            ['bs' => 5025, 'rate' => 50.25],
        ];

        foreach ($testCases as $case) {
            $bs = $case['bs'];
            $rate = $case['rate'];

            // Resultado esperado usando la política canónica
            $expected = $this->fromVesMinor($bs, $rate);

            // Verificar que DashboardService produce el mismo resultado
            $dashboardResult = $this->invokeDashboardFromVesMinor($bs, $rate);

            $this->assertEquals(
                $expected,
                $dashboardResult,
                "DashboardService::fromVesMinor({$bs}, {$rate}) debería ser {$expected}, pero fue {$dashboardResult}"
            );

            // Verificar que DebtAnalysisService produce el mismo resultado
            $debtResult = $this->invokeDebtFromVesMinor($bs, $rate);

            $this->assertEquals(
                $expected,
                $debtResult,
                "DebtAnalysisService::fromVesMinor({$bs}, {$rate}) debería ser {$expected}, pero fue {$debtResult}"
            );
        }
    }

    /**
     * Test que verifica el cálculo de outstanding.
     *
     * Outstanding = max(0, deuda_bs - pagado_bs), luego convertir a EUR.
     */
    public function test_outstanding_calculation(): void
    {
        $rate = 50.25;

        // Caso 1: Deuda completa, sin pagos
        // €100.00 deuda, 0 pagado => €100.00 outstanding
        $outstanding = $this->calculateOutstanding(10000, 0, $rate);
        $this->assertEquals(10000, $outstanding['eur']);
        $this->assertEquals(502500, $outstanding['bs']);

        // Caso 2: Deuda parcialmente pagada
        // €100.00 deuda (502500 Bs), 250000 Bs pagado => outstanding 252500 Bs => ~€50.24
        $outstanding = $this->calculateOutstanding(10000, 250000, $rate);
        $this->assertEquals(252500, $outstanding['bs']);
        // 252500 * 100 / 50.25 = 502487.56... => round => 502488 / 100 = 5024
        $this->assertEquals(5024, $outstanding['eur']);

        // Caso 3: Deuda completamente pagada
        // €100.00 deuda (502500 Bs), 502500 Bs pagado => 0 outstanding
        $outstanding = $this->calculateOutstanding(10000, 502500, $rate);
        $this->assertEquals(0, $outstanding['bs']);
        $this->assertEquals(0, $outstanding['eur']);

        // Caso 4: Sobrepago (pagó más que la deuda)
        // €100.00 deuda (502500 Bs), 600000 Bs pagado => 0 outstanding (no negativo)
        $outstanding = $this->calculateOutstanding(10000, 600000, $rate);
        $this->assertEquals(0, $outstanding['bs']);
        $this->assertEquals(0, $outstanding['eur']);
    }

    /**
     * Test que verifica consistencia con FxConversionHelper::toVes.
     */
    public function test_consistency_with_fx_conversion_helper_to_ves(): void
    {
        // Crear mock tipado correctamente como FxRate
        $rateMock = Mockery::mock(\App\Models\FxRate::class);
        $rateMock->shouldReceive('getAttribute')
            ->with('rate_to_ves')
            ->andReturn(50.25);

        $this->fxServiceMock->shouldReceive('resolveAt')
            ->andReturn($rateMock);

        $helper = new FxConversionHelper($this->fxServiceMock);
        $date = new \DateTimeImmutable('2025-01-15');

        // Comparar con la política canónica
        $testCases = [10000, 32519, 100, 99999, 1];

        foreach ($testCases as $amount) {
            $helperResult = $helper->toVes($amount, 'EUR', $date);
            $canonicalResult = $this->toVesMinor($amount, 50.25);

            $this->assertEquals(
                $canonicalResult,
                $helperResult,
                "FxConversionHelper::toVes({$amount}, 'EUR') debería ser {$canonicalResult}, pero fue {$helperResult}"
            );
        }
    }

    /**
     * Test que verifica consistencia con FxConversionHelper::fromVes.
     */
    public function test_consistency_with_fx_conversion_helper_from_ves(): void
    {
        $rateMock = Mockery::mock(\App\Models\FxRate::class);
        $rateMock->shouldReceive('getAttribute')
            ->with('rate_to_ves')
            ->andReturn(50.25);

        $this->fxServiceMock->shouldReceive('resolveAt')
            ->andReturn($rateMock);

        $helper = new FxConversionHelper($this->fxServiceMock);
        $date = new \DateTimeImmutable('2025-01-15');

        $testCases = [502500, 500000, 28350, 99999, 5025];

        foreach ($testCases as $bs) {
            $helperResult = $helper->fromVes($bs, 'EUR', $date);
            $canonicalResult = $this->fromVesMinor($bs, 50.25);

            $this->assertEquals(
                $canonicalResult,
                $helperResult,
                "FxConversionHelper::fromVes({$bs}, 'EUR') debería ser {$canonicalResult}, pero fue {$helperResult}"
            );
        }
    }

    // ===== Helper Methods =====

    /**
     * Política canónica de conversión toVes (la fuente de verdad).
     */
    private function toVesMinor(int $amountMinor, float $rate): int
    {
        if ($amountMinor <= 0 || $rate <= 0) {
            return 0;
        }

        $rateMinor = (int) round($rate * 100);
        $prod = $amountMinor * $rateMinor;

        return (int) intdiv($prod, 100);
    }

    /**
     * Política canónica de conversión fromVes (la fuente de verdad).
     */
    private function fromVesMinor(int $bsMinor, float $rate): int
    {
        if ($bsMinor <= 0 || $rate <= 0) {
            return 0;
        }

        $prod = (int) round(($bsMinor * 100) / $rate);

        return (int) intdiv($prod, 100);
    }

    /**
     * Cálculo canónico de outstanding.
     */
    private function calculateOutstanding(int $debtEurMinor, int $paidBsMinor, float $rate): array
    {
        $debtBsMinor = $this->toVesMinor($debtEurMinor, $rate);
        $outstandingBs = max(0, $debtBsMinor - $paidBsMinor);
        $outstandingEur = $this->fromVesMinor($outstandingBs, $rate);

        return ['eur' => $outstandingEur, 'bs' => $outstandingBs];
    }

    /**
     * Invocar método privado toVesMinor en EconomicProfileService.
     */
    private function invokeToVesMinorOn(string $class, int $amount, float $rate): ?int
    {
        $container = Mockery::mock(Container::class);
        $service = new EconomicProfileService($container);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('toVesMinor');
        $method->setAccessible(true);

        return $method->invoke($service, $amount, $rate);
    }

    /**
     * Invocar método privado toVesMinor en DashboardService.
     */
    private function invokeDashboardToVesMinor(int $amount, float $rate): int
    {
        $service = new DashboardService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('toVesMinor');
        $method->setAccessible(true);

        return $method->invoke($service, $amount, $rate);
    }

    /**
     * Invocar método privado fromVesMinor en DashboardService.
     */
    private function invokeDashboardFromVesMinor(int $bs, float $rate): int
    {
        $service = new DashboardService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('fromVesMinor');
        $method->setAccessible(true);

        return $method->invoke($service, $bs, $rate);
    }

    /**
     * Invocar método privado toVesMinor en DebtAnalysisService.
     */
    private function invokeDebtToVesMinor(int $amount, float $rate): int
    {
        $service = new DebtAnalysisService($this->fxServiceMock);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('toVesMinor');
        $method->setAccessible(true);

        return $method->invoke($service, $amount, $rate);
    }

    /**
     * Invocar método privado fromVesMinor en DebtAnalysisService.
     */
    private function invokeDebtFromVesMinor(int $bs, float $rate): int
    {
        $service = new DebtAnalysisService($this->fxServiceMock);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('fromVesMinor');
        $method->setAccessible(true);

        return $method->invoke($service, $bs, $rate);
    }
}
