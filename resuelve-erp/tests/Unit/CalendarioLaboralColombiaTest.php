<?php

namespace Tests\Unit;

use App\Domain\Pqrs\CalendarioLaboralColombia;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalendarioLaboralColombiaTest extends TestCase
{
    private CalendarioLaboralColombia $calendario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendario = new CalendarioLaboralColombia();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('casosSuma')]
    public function test_suma_dias_habiles_desde_el_dia_siguiente(string $radicacion, int $dias, string $esperada): void
    {
        $this->assertSame($esperada, $this->calendario->sumarDiasHabiles($radicacion, $dias)->toDateString());
    }

    public static function casosSuma(): array
    {
        return [
            'lunes' => ['2026-02-02', 1, '2026-02-03'],
            'jueves' => ['2026-02-05', 1, '2026-02-06'],
            'viernes' => ['2026-02-06', 1, '2026-02-09'],
            'sábado' => ['2026-02-07', 1, '2026-02-09'],
            'domingo' => ['2026-02-08', 1, '2026-02-09'],
            'festivo entre semana' => ['2026-04-29', 2, '2026-05-04'],
            'lunes festivo' => ['2026-01-09', 1, '2026-01-13'],
            'fin de semana y lunes festivo' => ['2026-01-10', 1, '2026-01-13'],
            'varios festivos de Semana Santa' => ['2026-04-01', 2, '2026-04-07'],
            'cambio de mes' => ['2026-01-30', 1, '2026-02-02'],
            'cambio de año' => ['2026-12-31', 1, '2027-01-04'],
            'año bisiesto' => ['2024-02-28', 2, '2024-03-01'],
            'un día configurado' => ['2026-08-12', 1, '2026-08-13'],
            'quince días configurados' => ['2026-08-12', 15, '2026-09-03'],
        ];
    }

    public function test_reconoce_festivos_fijos_trasladables_y_dependientes_de_pascua(): void
    {
        foreach (['2026-01-01', '2026-07-20', '2026-12-25'] as $fecha) {
            $this->assertTrue($this->calendario->esFestivo($fecha), $fecha);
        }
        foreach (['2026-01-12', '2026-03-23', '2026-08-17', '2026-11-16'] as $fecha) {
            $this->assertTrue($this->calendario->esFestivo($fecha), $fecha);
        }
        foreach (['2026-04-02', '2026-04-03', '2026-05-18', '2026-06-08', '2026-06-15'] as $fecha) {
            $this->assertTrue($this->calendario->esFestivo($fecha), $fecha);
        }
        $this->assertFalse($this->calendario->esFestivo('2026-04-06'));
    }

    public function test_hoy_opera_expresamente_en_bogota_cerca_de_medianoche_utc(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 03:30:00 UTC'));

        $this->assertSame('2026-08-12', $this->calendario->hoy()->toDateString());
        $this->assertSame(CalendarioLaboralColombia::ZONA_HORARIA, $this->calendario->hoy()->timezoneName);

    }

    public function test_rechaza_cantidades_no_positivas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calendario->sumarDiasHabiles('2026-01-01', 0);
    }

    #[DataProvider('casosDiferencia')]
    public function test_diferencia_dias_habiles_conserva_signo_y_excluye_no_habiles(string $desde, string $hasta, int $esperada): void
    {
        $this->assertSame($esperada, $this->calendario->diferenciaDiasHabiles($desde, $hasta));
    }

    public static function casosDiferencia(): array
    {
        return [
            'futuro con fin de semana' => ['2026-02-06', '2026-02-09', 1],
            'futuro con festivo' => ['2026-08-14', '2026-08-18', 1],
            'vence hoy' => ['2026-08-18', '2026-08-18', 0],
            'vencida con fin de semana y festivo' => ['2026-08-18', '2026-08-14', -1],
            'plural futuro' => ['2026-08-12', '2026-08-24', 7],
            'plural vencido' => ['2026-08-18', '2026-08-07', -6],
        ];
    }
}
