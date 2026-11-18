@extends('layouts.app')

@php
    $tipo = old('tipo', $tipoInicial);
    $consumibleSeleccionado = (int) old('consumible_id', $consumibleInicial);
@endphp

@section('titulo', 'Registrar movimiento')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('movimientos.index') }}">Movimientos</a></li>
            <li class="breadcrumb-item active" aria-current="page">Registrar</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Registrar movimiento</h1>

    <form method="POST" action="{{ route('movimientos.store') }}" novalidate class="card card-body">
        @csrf

        <div class="row g-3">
            <div class="col-md-4">
                <label for="tipo" class="form-label">Tipo de movimiento</label>
                <select id="tipo" name="tipo" class="form-select" required>
                    <option value="entrada" @selected($tipo === 'entrada')>Entrada sin compra</option>
                    <option value="salida" @selected($tipo === 'salida')>Salida a una impresora</option>
                    <option value="ajuste" @selected($tipo === 'ajuste')>Ajuste de existencias</option>
                </select>
                <div class="form-text" data-solo="entrada">
                    Las recepciones de compras se registran desde la propia compra.
                </div>
            </div>

            <div class="col-md-5">
                <label for="consumible_id" class="form-label">Consumible</label>
                <select id="consumible_id" name="consumible_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($consumibles as $consumible)
                        <option value="{{ $consumible->id }}" @selected($consumibleSeleccionado === $consumible->id)>
                            {{ $consumible->codigo }} · Existencias: {{ $consumible->existencia?->cantidad ?? 0 }}{{ $consumible->activo ? '' : ' (inactivo)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="cantidad" class="form-label">Cantidad</label>
                <input id="cantidad" type="number" name="cantidad" value="{{ old('cantidad') }}"
                       class="form-control" required>
                <div class="form-text" data-solo="ajuste">Positiva suma; negativa resta.</div>
            </div>

            <div class="col-md-6" data-solo="salida">
                <label for="impresora_id" class="form-label">Impresora de destino</label>
                <select id="impresora_id" name="impresora_id" class="form-select">
                    <option value="">Seleccione…</option>
                    @foreach ($impresoras as $impresora)
                        <option value="{{ $impresora->id }}" @selected((int) old('impresora_id') === $impresora->id)>
                            {{ $impresora->nombre }} · {{ $impresora->area->lugar->zona->nombre }} › {{ $impresora->area->nombre }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Solo aparecen impresoras operativas.</div>
            </div>

            <div class="col-md-6">
                <label for="ticket" class="form-label">Ticket</label>
                <input id="ticket" type="text" name="ticket" maxlength="100" value="{{ old('ticket') }}"
                       class="form-control">
                <div class="form-text">Opcional. Por ejemplo, R-082061.</div>
            </div>

            <div class="col-12" data-solo="entrada ajuste">
                <label for="motivo" class="form-label">Motivo</label>
                <textarea id="motivo" name="motivo" rows="2" maxlength="500"
                          class="form-control">{{ old('motivo') }}</textarea>
                <div class="form-text">Obligatorio. Por ejemplo, stock inicial, donación o conteo físico.</div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Registrar</button>
            <a href="{{ route('movimientos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    <script>
        // Muestra solo los campos del tipo elegido; los ocultos no se envían.
        document.addEventListener('DOMContentLoaded', function () {
            const tipo = document.getElementById('tipo');

            function actualizar() {
                document.querySelectorAll('[data-solo]').forEach(function (bloque) {
                    const visible = bloque.dataset.solo.split(' ').includes(tipo.value);
                    bloque.classList.toggle('d-none', !visible);
                    bloque.querySelectorAll('input, select, textarea').forEach(function (campo) {
                        campo.disabled = !visible;
                    });
                });
            }

            tipo.addEventListener('change', actualizar);
            actualizar();
        });
    </script>
@endsection