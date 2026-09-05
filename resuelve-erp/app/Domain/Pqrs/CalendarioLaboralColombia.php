<?php

namespace App\Domain\Pqrs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;

final class CalendarioLaboralColombia
{
    public const ZONA_HORARIA = 'America/Bogota';

    public function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA_HORARIA)->startOfDay();
    }

    public function esDiaHabil(DateTimeInterface|string $fecha): bool
    {
        $dia = $this->fecha($fecha);

        return ! $dia->isWeekend() && ! $this->esFestivo($dia);
    }

    public function sumarDiasHabiles(DateTimeInterface|string $fechaRadicacion, int $dias): CarbonImmutable
    {
        if ($dias < 1) {
            throw new InvalidArgumentException('La cantidad de días hábiles debe ser mayor que cero.');
        }

        $fecha = $this->fecha($fechaRadicacion);
        $contados = 0;

        while ($contados < $dias) {
            $fecha = $fecha->addDay();
            if ($this->esDiaHabil($fecha)) {
                $contados++;
            }
        }

        return $fecha;
    }

    public function diferenciaDiasHabiles(DateTimeInterface|string $desde, DateTimeInterface|string $hasta): int
    {
        $inicio = $this->fecha($desde);
        $fin = $this->fecha($hasta);

        if ($inicio->equalTo($fin)) {
            return 0;
        }

        $signo = $inicio->lessThan($fin) ? 1 : -1;
        $cursor = $signo === 1 ? $inicio : $fin;
        $limite = $signo === 1 ? $fin : $inicio;
        $dias = 0;

        while ($cursor->lessThan($limite)) {
            $cursor = $cursor->addDay();
            if ($this->esDiaHabil($cursor)) {
                $dias++;
            }
        }

        return $dias * $signo;
    }

    public function esFestivo(DateTimeInterface|string $fecha): bool
    {
        $dia = $this->fecha($fecha);

        return isset($this->festivosDelAnio($dia->year)[$dia->toDateString()]);
    }

    /** @return array<string, true> */
    private function festivosDelAnio(int $anio): array
    {
        $festivos = [];
        $agregar = static function (CarbonImmutable $fecha) use (&$festivos): void {
            $festivos[$fecha->toDateString()] = true;
        };

        // Fechas fijas nacionales.
        foreach ([[1, 1], [5, 1], [7, 20], [8, 7], [12, 8], [12, 25]] as [$mes, $dia]) {
            $agregar(CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0, self::ZONA_HORARIA));
        }

        // Ley 51 de 1983 (Ley Emiliani): se observan el lunes siguiente o el mismo lunes.
        foreach ([[1, 6], [3, 19], [6, 29], [8, 15], [10, 12], [11, 1], [11, 11]] as [$mes, $dia]) {
            $agregar($this->lunesDeObservancia(CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0, self::ZONA_HORARIA)));
        }

        $pascua = CarbonImmutable::create($anio, 3, 21, 0, 0, 0, self::ZONA_HORARIA)
            ->addDays(easter_days($anio));
        $agregar($pascua->subDays(3)); // Jueves Santo.
        $agregar($pascua->subDays(2)); // Viernes Santo.
        foreach ([39, 60, 68] as $desplazamiento) {
            $agregar($this->lunesDeObservancia($pascua->addDays($desplazamiento)));
        }

        return $festivos;
    }

    private function lunesDeObservancia(CarbonImmutable $fecha): CarbonImmutable
    {
        return $fecha->isMonday() ? $fecha : $fecha->next(CarbonInterface::MONDAY);
    }

    private function fecha(DateTimeInterface|string $fecha): CarbonImmutable
    {
        if ($fecha instanceof DateTimeInterface) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $fecha->format('Y-m-d'), self::ZONA_HORARIA);
        }

        return CarbonImmutable::parse($fecha, self::ZONA_HORARIA)
            ->startOfDay();
    }
}
