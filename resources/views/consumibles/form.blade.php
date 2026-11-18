@extends('layouts.app')

@php
    $editando = $consumible->exists;
    $activo = (string) old('activo', $editando ? (int) $consumible->activo : 1);
    $marcados = array_map('intval', (array) old('modelos', $seleccionados));
    $gruposModelos = $modelos->groupBy(fn ($modelo) => $modelo->marca->nombre);
@endphp

@section('titulo', $editando ? 'Editar consumible' : 'Nuevo consumible')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('consumibles.index') }}">Consumibles</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editando ? 'Editar' : 'Nuevo' }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $editando ? 'Editar consumible' : 'Nuevo consumible' }}</h1>

    <form method="POST" novalidate class="card card-body"
          action="{{ $editando ? route('consumibles.update', $consumible) : route('consumibles.store') }}">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label for="tipo_consumible_id" class="form-label">Tipo</label>
                <select id="tipo_consumible_id" name="tipo_consumible_id"
                        class="form-select @error('tipo_consumible_id') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}"
                                @selected((int) old('tipo_consumible_id', $consumible->tipo_consumible_id) === $tipo->id)>
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('tipo_consumible_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="codigo" class="form-label">Código</label>
                <input id="codigo" type="text" name="codigo" maxlength="50"
                       value="{{ old('codigo', $consumible->codigo) }}"
                       class="form-control @error('codigo') is-invalid @enderror" required>
                @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="color" class="form-label">Color</label>
                <select id="color" name="color" class="form-select @error('color') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach (\App\Modules\Inventory\Models\Consumible::COLORES as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('color', $consumible->color) === $valor)>
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
                @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción</label>
                <input id="descripcion" type="text" name="descripcion" maxlength="255"
                       value="{{ old('descripcion', $consumible->descripcion) }}"
                       class="form-control @error('descripcion') is-invalid @enderror">
                @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="rendimiento_paginas" class="form-label">Rendimiento (páginas)</label>
                <input id="rendimiento_paginas" type="number" name="rendimiento_paginas" min="1"
                       value="{{ old('rendimiento_paginas', $consumible->rendimiento_paginas) }}"
                       class="form-control @error('rendimiento_paginas') is-invalid @enderror">
                @error('rendimiento_paginas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Opcional.</div>
            </div>

            <div class="col-md-4">
                <label for="stock_minimo" class="form-label">Stock mínimo</label>
                <input id="stock_minimo" type="number" name="stock_minimo" min="0"
                       value="{{ old('stock_minimo', $consumible->stock_minimo ?? 0) }}"
                       class="form-control @error('stock_minimo') is-invalid @enderror" required>
                @error('stock_minimo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Por debajo de este valor se marca «Stock bajo». Con 0 no hay alerta.</div>
            </div>

            <div class="col-md-4">
                @if ($editando)
                    <label class="form-label">Existencias actuales</label>
                    <div class="form-control-plaintext">{{ $consumible->existencia?->cantidad ?? 0 }}</div>
                    <div class="form-text">Solo cambian con movimientos de inventario.</div>
                @endif
                <div class="form-check mt-2">
                    <input type="hidden" name="activo" value="0">
                    <input id="activo" type="checkbox" class="form-check-input" name="activo" value="1"
                           @checked($activo === '1')>
                    <label for="activo" class="form-check-label">Consumible activo</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Modelos de impresora compatibles</label>
                @error('modelos')<div class="text-danger small">{{ $message }}</div>@enderror
                @error('modelos.*')<div class="text-danger small">{{ $message }}</div>@enderror

                <div class="row">
                    @foreach ($gruposModelos as $marca => $items)
                        <div class="col-md-4 mb-3">
                            <div class="fw-semibold">{{ $marca }}</div>
                            @foreach ($items as $modelo)
                                <div class="form-check">
                                    <input id="modelo-{{ $modelo->id }}" type="checkbox" class="form-check-input"
                                           name="modelos[]" value="{{ $modelo->id }}"
                                           @checked(in_array($modelo->id, $marcados, true))>
                                    <label for="modelo-{{ $modelo->id }}" class="form-check-label">{{ $modelo->nombre }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('consumibles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection