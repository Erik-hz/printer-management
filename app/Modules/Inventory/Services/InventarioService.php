<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\Existencia;
use App\Modules\Inventory\Models\MovimientoInventario;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Purchasing\Models\Compra;
use App\Modules\Purchasing\Models\DetalleCompra;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto de entrada para modificar existencias. Cada operación se ejecuta en una
 * transacción, bloquea la fila de existencias y registra un movimiento inmutable.
 */
class InventarioService
{
    public function registrarRecepcion(
        int $detalleCompraId,
        int $cantidad,
        int $usuarioId,
        ?string $ticket = null,
        ?DateTimeInterface $fechaMovimiento = null,
    ): MovimientoInventario {
        $this->validarCantidadPositiva($cantidad);
        $ticket = $this->normalizarTicket($ticket);

        return DB::transaction(function () use (
            $detalleCompraId,
            $cantidad,
            $usuarioId,
            $ticket,
            $fechaMovimiento,
        ): MovimientoInventario {
            $detalleReferencia = DetalleCompra::query()->findOrFail($detalleCompraId);
            $compra = Compra::query()
                ->lockForUpdate()
                ->findOrFail($detalleReferencia->compra_id);
            $detalle = DetalleCompra::query()->lockForUpdate()->findOrFail($detalleCompraId);

            if ((int) $detalle->compra_id !== (int) $compra->getKey()) {
                throw ValidationException::withMessages([
                    'detalle_compra_id' => 'El detalle de compra cambió durante la recepción.',
                ]);
            }

            if (! in_array($compra->estado, ['ordenada', 'parcialmente_recibida'], true)) {
                throw ValidationException::withMessages([
                    'compra' => 'No se puede recibir una compra cancelada o ya recibida.',
                ]);
            }

            $pendiente = (int) $detalle->cantidad - (int) $detalle->cantidad_recibida;

            if ($cantidad > $pendiente) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La recepción supera la cantidad pendiente del detalle.',
                ]);
            }

            $existencia = $this->existenciaBloqueada((int) $detalle->consumible_id);
            $existencia->cantidad += $cantidad;
            $existencia->save();

            $detalle->cantidad_recibida += $cantidad;
            $detalle->save();

            $movimiento = $this->crearMovimiento([
                'consumible_id' => $detalle->consumible_id,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'saldo_resultante' => $existencia->cantidad,
                'detalle_compra_id' => $detalle->getKey(),
                'usuario_id' => $usuarioId,
                'ticket' => $ticket,
                'fecha_movimiento' => $fechaMovimiento ?? now(),
            ]);

            $cantidadOrdenada = (int) $compra->detalles()->sum('cantidad');
            $cantidadRecibida = (int) $compra->detalles()->sum('cantidad_recibida');
            $compra->estado = $cantidadRecibida >= $cantidadOrdenada
                ? 'recibida'
                : 'parcialmente_recibida';
            $compra->save();

            return $movimiento;
        });
    }

    /**
     * Entrada sin compra (stock inicial, donación...). Exige un motivo escrito y un consumible activo.
     */
    public function registrarEntradaManual(
        int $consumibleId,
        int $cantidad,
        string $motivo,
        int $usuarioId,
        ?string $ticket = null,
        ?DateTimeInterface $fechaMovimiento = null,
    ): MovimientoInventario {
        $this->validarCantidadPositiva($cantidad);
        $motivo = $this->motivoObligatorio(
            $motivo,
            'La entrada sin compra requiere un motivo (por ejemplo, stock inicial o donación).'
        );
        $ticket = $this->normalizarTicket($ticket);

        return DB::transaction(function () use (
            $consumibleId,
            $cantidad,
            $motivo,
            $usuarioId,
            $ticket,
            $fechaMovimiento,
        ): MovimientoInventario {
            $this->validarConsumible($consumibleId, exigirActivo: true);

            $existencia = $this->existenciaBloqueada($consumibleId);
            $existencia->cantidad += $cantidad;
            $existencia->save();

            return $this->crearMovimiento([
                'consumible_id' => $consumibleId,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'saldo_resultante' => $existencia->cantidad,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'ticket' => $ticket,
                'fecha_movimiento' => $fechaMovimiento ?? now(),
            ]);
        });
    }

    /**
     * Salida de almacén. La impresora de destino es obligatoria en la pantalla de registro,
     * pero este servicio acepta null para las cargas históricas del Excel (Fase 7), donde
     * el destino puede no constar. Si se indica, debe existir y estar operativa.
     */
    public function registrarSalida(
        int $consumibleId,
        int $cantidad,
        int $usuarioId,
        ?int $impresoraId = null,
        ?string $ticket = null,
        ?DateTimeInterface $fechaMovimiento = null,
    ): MovimientoInventario {
        $this->validarCantidadPositiva($cantidad);
        $ticket = $this->normalizarTicket($ticket);

        return DB::transaction(function () use (
            $consumibleId,
            $cantidad,
            $usuarioId,
            $impresoraId,
            $ticket,
            $fechaMovimiento,
        ): MovimientoInventario {
            $this->validarConsumible($consumibleId);

            if ($impresoraId !== null) {
                $this->validarImpresoraDestino($impresoraId);
            }

            $existencia = $this->existenciaBloqueada($consumibleId);

            if ($existencia->cantidad < $cantidad) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La salida supera las existencias disponibles.',
                ]);
            }

            $existencia->cantidad -= $cantidad;
            $existencia->save();

            return $this->crearMovimiento([
                'consumible_id' => $consumibleId,
                'tipo' => 'salida',
                'cantidad' => -$cantidad,
                'saldo_resultante' => $existencia->cantidad,
                'impresora_id' => $impresoraId,
                'usuario_id' => $usuarioId,
                'ticket' => $ticket,
                'fecha_movimiento' => $fechaMovimiento ?? now(),
            ]);
        });
    }

    public function registrarAjuste(
        int $consumibleId,
        int $cantidad,
        string $motivo,
        int $usuarioId,
        ?string $ticket = null,
        ?DateTimeInterface $fechaMovimiento = null,
    ): MovimientoInventario {
        if ($cantidad === 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'Un ajuste debe ser distinto de cero.',
            ]);
        }

        $motivo = $this->motivoObligatorio($motivo, 'El motivo del ajuste es obligatorio.');
        $ticket = $this->normalizarTicket($ticket);

        return DB::transaction(function () use (
            $consumibleId,
            $cantidad,
            $motivo,
            $usuarioId,
            $ticket,
            $fechaMovimiento,
        ): MovimientoInventario {
            $this->validarConsumible($consumibleId);

            $existencia = $this->existenciaBloqueada($consumibleId);
            $nuevoSaldo = $existencia->cantidad + $cantidad;

            if ($nuevoSaldo < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'El ajuste no puede dejar existencias negativas.',
                ]);
            }

            $existencia->cantidad = $nuevoSaldo;
            $existencia->save();

            return $this->crearMovimiento([
                'consumible_id' => $consumibleId,
                'tipo' => 'ajuste',
                'cantidad' => $cantidad,
                'saldo_resultante' => $nuevoSaldo,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'ticket' => $ticket,
                'fecha_movimiento' => $fechaMovimiento ?? now(),
            ]);
        });
    }

    private function existenciaBloqueada(int $consumibleId): Existencia
    {
        $existencia = Existencia::query()
            ->lockForUpdate()
            ->find($consumibleId);

        if ($existencia === null) {
            throw ValidationException::withMessages([
                'consumible_id' => 'El consumible no tiene una fila de existencias inicializada.',
            ]);
        }

        return $existencia;
    }

    /** El consumible debe existir (no estar eliminado). Para ingresar stock, además, estar activo. */
    private function validarConsumible(int $consumibleId, bool $exigirActivo = false): void
    {
        $consumible = Consumible::query()->find($consumibleId);

        if ($consumible === null) {
            throw ValidationException::withMessages([
                'consumible_id' => 'El consumible no existe o fue eliminado.',
            ]);
        }

        if ($exigirActivo && ! $consumible->activo) {
            throw ValidationException::withMessages([
                'consumible_id' => 'No se puede ingresar stock a un consumible inactivo. Actívelo primero.',
            ]);
        }
    }

    /** La impresora debe existir y estar operativa; una retirada no recibe consumibles. */
    private function validarImpresoraDestino(int $impresoraId): void
    {
        $valida = Impresora::query()
            ->whereKey($impresoraId)
            ->whereHas('estado', fn ($estado) => $estado->where('es_operativo', true))
            ->exists();

        if (! $valida) {
            throw ValidationException::withMessages([
                'impresora_id' => 'La impresora indicada no existe o no está operativa (por ejemplo, está retirada).',
            ]);
        }
    }

    private function motivoObligatorio(string $motivo, string $mensaje): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => $mensaje]);
        }

        return $motivo;
    }

    private function normalizarTicket(?string $ticket): ?string
    {
        $ticket = $ticket === null ? null : trim($ticket);

        return $ticket === '' ? null : $ticket;
    }

    private function validarCantidadPositiva(int $cantidad): void
    {
        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser mayor que cero.',
            ]);
        }
    }

    private function crearMovimiento(array $attributes): MovimientoInventario
    {
        return MovimientoInventario::query()->create($attributes);
    }
}