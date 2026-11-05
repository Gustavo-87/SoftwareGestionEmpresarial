<?php

namespace App\Application\Residencia;

use App\Application\Contexto\ContextoOperativo;
use App\Models\AuditLog;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Importación masiva de Unidades Privadas desde XLSX (HU-RYP-03, Sprint 22).
 *
 * Solo altas: no modifica ni elimina registros. Cada fila se valida con las
 * reglas del alta individual (`GestionarUnidadPrivada`) y una fila inválida no
 * impide procesar las demás. Los códigos duplicados —dentro del archivo o ya
 * existentes en la Copropiedad, insensible a mayúsculas— se omiten y se
 * reportan; la colisión con el índice único durante la concurrencia se trata
 * como duplicada omitida. La Organización y la Copropiedad se resuelven del
 * contexto activo en backend, nunca del archivo. El libro se carga en memoria
 * (PhpSpreadsheet) en modo solo lectura de datos: los límites de filas y de
 * tamaño reducen el riesgo de memoria, pero no lo eliminan.
 */
final class ImportarUnidadesPrivadas
{
    /** Máximo de filas de datos por archivo, sin contar el encabezado. */
    public const FILAS_MAXIMAS = 1000;

    public function __construct(private readonly GestionarUnidadPrivada $unidades) {}

    /**
     * @return array{
     *     estado: 'exito'|'parcial'|'error',
     *     leidas: int,
     *     creadas: int,
     *     duplicadas: int,
     *     invalidas: int,
     *     errores: list<array{fila: int, campo: ?string, motivo: string}>,
     * }
     */
    public function ejecutar(ContextoOperativo $contexto, User $operador, UploadedFile $archivo): array
    {
        AutorizacionResidencia::exigirGestion($contexto);

        $filas = $this->leerFilas($archivo);

        $vistos = [];
        $creadas = 0;
        $duplicadas = 0;
        $invalidas = 0;
        $errores = [];

        foreach ($filas as $fila) {
            $numeroFila = $fila['fila'];
            $datos = $fila['datos'];
            $codigo = mb_strtolower(trim((string) ($datos['codigo'] ?? '')));

            if ($codigo !== '' && isset($vistos[$codigo])) {
                $duplicadas++;
                $errores[] = [
                    'fila' => $numeroFila,
                    'campo' => 'codigo',
                    'motivo' => 'Código repetido en el archivo (fila '.$vistos[$codigo].'); la fila se omitió.',
                ];

                continue;
            }

            if ($codigo !== '' && $this->unidades->codigoExistente($contexto, $codigo)) {
                $vistos[$codigo] = $numeroFila;
                $duplicadas++;
                $errores[] = [
                    'fila' => $numeroFila,
                    'campo' => 'codigo',
                    'motivo' => 'Ya existe una unidad con este código en la Copropiedad; la fila se omitió.',
                ];

                continue;
            }

            try {
                // Reutiliza las reglas del alta individual, la transacción
                // interna y la auditoría por unidad creada.
                $this->unidades->crear($contexto, $operador, $datos);
                $vistos[$codigo] = $numeroFila;
                $creadas++;
            } catch (ValidationException $exception) {
                $vistos[$codigo] = $numeroFila;

                if ($this->esCodigoDuplicado($exception)) {
                    // Colisión con el índice único durante la concurrencia.
                    $duplicadas++;
                    $errores[] = [
                        'fila' => $numeroFila,
                        'campo' => 'codigo',
                        'motivo' => 'Ya existe una unidad con este código en la Copropiedad; la fila se omitió.',
                    ];

                    continue;
                }

                $invalidas++;
                $campo = array_key_first($exception->errors());
                $errores[] = [
                    'fila' => $numeroFila,
                    'campo' => $campo,
                    'motivo' => (string) ($exception->errors()[$campo][0] ?? 'Fila inválida.'),
                ];
            }
        }

        $leidas = count($filas);
        $resultado = [
            'estado' => match (true) {
                $creadas === 0 => 'error',
                $invalidas > 0 || $duplicadas > 0 => 'parcial',
                default => 'exito',
            },
            'leidas' => $leidas,
            'creadas' => $creadas,
            'duplicadas' => $duplicadas,
            'invalidas' => $invalidas,
            'errores' => $errores,
        ];

        $this->auditar($contexto, $operador, $archivo, $resultado);

        return $resultado;
    }

    /**
     * Lee la primera hoja del XLSX y devuelve las filas de datos con sus
     * campos normalizados. Rechaza el archivo completo —antes de crear nada—
     * si excede el máximo de filas o no tiene la columna «Código».
     *
     * @return list<array{fila: int, datos: array{codigo: ?string, numero_nombre: ?string, torre_bloque: ?string, tipo: ?string}}>
     */
    private function leerFilas(UploadedFile $archivo): array
    {
        try {
            $lector = IOFactory::createReaderForFile($archivo->getRealPath());
            $lector->setReadDataOnly(true);
            $libro = $lector->load($archivo->getRealPath());
            /** @var Worksheet $hoja */
            $hoja = $libro->getActiveSheet();

            $columnas = $this->columnas($hoja);
            $ultimaFila = (int) $hoja->getHighestDataRow();

            if ($ultimaFila < 2) {
                return [];
            }

            if ($ultimaFila - 1 > self::FILAS_MAXIMAS) {
                throw ValidationException::withMessages([
                    'archivo' => 'El archivo excede el máximo de '.self::FILAS_MAXIMAS.' filas de datos.',
                ]);
            }

            $filas = [];
            for ($fila = 2; $fila <= $ultimaFila; $fila++) {
                $vacia = true;
                $datos = ['codigo' => null, 'numero_nombre' => null, 'torre_bloque' => null, 'tipo' => null];
                foreach ($columnas as $campo => $columna) {
                    $valor = trim((string) $hoja->getCell($columna.$fila)->getFormattedValue());
                    if ($valor !== '') {
                        $vacia = false;
                    }
                    $datos[$campo] = $valor === '' ? null : $valor;
                }

                if (! $vacia) {
                    $filas[] = ['fila' => $fila, 'datos' => $datos];
                }
            }

            unset($libro);

            return $filas;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'archivo' => 'No fue posible leer el archivo. Asegúrate de que sea un XLSX válido.',
            ]);
        }
    }

    /** @return array<string, string> campo => letra de columna */
    private function columnas(Worksheet $hoja): array
    {
        $alias = [
            'codigo' => 'codigo',
            'numero_nombre' => 'numero_nombre',
            'numero' => 'numero_nombre',
            'nombre' => 'numero_nombre',
            'torre_bloque' => 'torre_bloque',
            'torre' => 'torre_bloque',
            'bloque' => 'torre_bloque',
            'tipo' => 'tipo',
        ];

        $columnas = [];
        foreach ($hoja->getRowIterator(1, 1)->current()->getCellIterator() as $celda) {
            $encabezado = $this->normalizarEncabezado((string) $celda->getFormattedValue());
            if (isset($alias[$encabezado])) {
                $columnas[$alias[$encabezado]] = $celda->getColumn();
            }
        }

        if (! isset($columnas['codigo'])) {
            throw ValidationException::withMessages([
                'archivo' => 'La primera fila debe contener la columna «Código».',
            ]);
        }

        return $columnas;
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[\s\/\-]+/', '_', $valor));
    }

    private function esCodigoDuplicado(ValidationException $exception): bool
    {
        return $exception->errors() === ['codigo' => [GestionarUnidadPrivada::MENSAJE_CODIGO_DUPLICADO]];
    }

    private function auditar(ContextoOperativo $contexto, User $operador, UploadedFile $archivo, array $resultado): void
    {
        AuditLog::create([
            'user_id' => $operador->id,
            'action' => 'residencia.unidad.importar',
            'auditable_type' => UnidadPrivada::class,
            'auditable_id' => null,
            'ip_address' => request()->ip(),
            'metadata' => [
                'archivo' => $archivo->getClientOriginalName(),
                'leidas' => $resultado['leidas'],
                'creadas' => $resultado['creadas'],
                'duplicadas' => $resultado['duplicadas'],
                'invalidas' => $resultado['invalidas'],
                'contexto' => [
                    'organizacion_id' => $contexto->organizacion->id,
                    'copropiedad_id' => $contexto->copropiedad->id,
                ],
            ],
        ]);
    }
}
