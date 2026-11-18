@extends('layouts.app')

@section('titulo', 'Nueva orden de compra')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('compras.index') }}">Órdenes de compra</a></li>
            <li class="breadcrumb-item active">Nueva orden</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Nueva orden de compra</h1>
    @if ($proveedores->isEmpty() || $consumibles->isEmpty())
        <div class="alert alert-warning">Se necesita al menos un proveedor y un consumible activos para crear una orden.</div>
    @endif

    <form method="POST" action="{{ route('compras.store') }}" class="card card-body">
        @csrf
        <div class="row g-3">
            <div class="col-md-5">
                <label for="proveedor_id" class="form-label">Proveedor</label>
                <select id="proveedor_id" name="proveedor_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" @selected((int) old('proveedor_id') === $proveedor->id)>{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
                @error('proveedor_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="fecha_orden" class="form-label">Fecha de orden</label>
                <input id="fecha_orden" type="date" name="fecha_orden" value="{{ old('fecha_orden', now()->toDateString()) }}" class="form-control" required>
                @error('fecha_orden')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="numero_documento" class="form-label">N.º documento</label>
                <input id="numero_documento" name="numero_documento" maxlength="100" value="{{ old('numero_documento') }}" class="form-control">
                @error('numero_documento')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <label for="moneda" class="form-label">Moneda</label>
                <input id="moneda" name="moneda" maxlength="3" value="{{ old('moneda', 'BOB') }}" class="form-control text-uppercase" required>
                @error('moneda')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <input type="hidden" name="incluye_iva" value="0">
                <div class="form-check mb-2">
                    <input id="incluye_iva" type="checkbox" name="incluye_iva" value="1" class="form-check-input" @checked(old('incluye_iva'))>
                    <label for="incluye_iva" class="form-check-label">IVA</label>
                </div>
            </div>
            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000" class="form-control">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h2 class="h5 mb-0">Consumibles</h2>
            <button id="agregar-detalle" type="button" class="btn btn-sm btn-outline-primary">Agregar línea</button>
        </div>
        @error('detalles')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('detalles.*.consumible_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('detalles.*.cantidad')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('detalles.*.precio_unitario')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div id="detalles">
            <div class="row g-2 align-items-end mb-2 detalle">
                <div class="col-md-6">
                    <label class="form-label">Consumible</label>
                    <select name="detalles[0][consumible_id]" class="form-select" required>
                        <option value="">Seleccione…</option>
                        @foreach ($consumibles as $consumible)
                            <option value="{{ $consumible->id }}">{{ $consumible->codigo }}{{ $consumible->descripcion ? ' · '.$consumible->descripcion : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="detalles[0][cantidad]" min="1" max="1000000" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio unitario</label>
                    <input type="number" name="detalles[0][precio_unitario]" min="0" max="9999999999.99" step="0.01" class="form-control" required>
                </div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger quitar-detalle" disabled>Quitar</button></div>
            </div>
        </div>

        <template id="plantilla-detalle">
            <div class="row g-2 align-items-end mb-2 detalle">
                <div class="col-md-6">
                    <label class="form-label">Consumible</label>
                    <select name="detalles[__INDEX__][consumible_id]" class="form-select" required>
                        <option value="">Seleccione…</option>
                        @foreach ($consumibles as $consumible)
                            <option value="{{ $consumible->id }}">{{ $consumible->codigo }}{{ $consumible->descripcion ? ' · '.$consumible->descripcion : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="detalles[__INDEX__][cantidad]" min="1" max="1000000" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio unitario</label>
                    <input type="number" name="detalles[__INDEX__][precio_unitario]" min="0" max="9999999999.99" step="0.01" class="form-control" required>
                </div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger quitar-detalle">Quitar</button></div>
            </div>
        </template>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary" @disabled($proveedores->isEmpty() || $consumibles->isEmpty())>Crear orden</button>
            <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lista = document.getElementById('detalles');
            const plantilla = document.getElementById('plantilla-detalle').innerHTML;
            let indice = 1;

            document.getElementById('agregar-detalle').addEventListener('click', function () {
                lista.insertAdjacentHTML('beforeend', plantilla.replace(/__INDEX__/g, indice++));
            });

            lista.addEventListener('click', function (evento) {
                if (evento.target.matches('.quitar-detalle') && lista.querySelectorAll('.detalle').length > 1) {
                    evento.target.closest('.detalle').remove();
                }
            });
        });
    </script>
@endsection
