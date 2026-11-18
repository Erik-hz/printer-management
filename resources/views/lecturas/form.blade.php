@extends('layouts.app')

@section('titulo', 'Nueva lectura')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('lecturas.index') }}">Lecturas</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nueva</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Registrar lectura manual</h1>

    <form method="POST" action="{{ route('lecturas.store') }}" novalidate class="card card-body">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label for="impresora_id" class="form-label">Impresora</label>
                <select id="impresora_id" name="impresora_id"
                        class="form-select @error('impresora_id') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach ($impresoras as $impresora)
                        <option value="{{ $impresora->id }}" @selected((int) old('impresora_id') === $impresora->id)>
                            {{ $impresora->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('impresora_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="fecha_lectura" class="form-label">Fecha y hora</label>
                <input id="fecha_lectura" type="datetime-local" name="fecha_lectura"
                       value="{{ old('fecha_lectura', now()->format('Y-m-d\\TH:i')) }}"
                       class="form-control @error('fecha_lectura') is-invalid @enderror" required>
                @error('fecha_lectura')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="contador_paginas" class="form-label">Contador de páginas</label>
                <input id="contador_paginas" type="number" name="contador_paginas" min="0"
                       value="{{ old('contador_paginas') }}"
                       class="form-control @error('contador_paginas') is-invalid @enderror"
                       placeholder="Ej. 12500">
                @error('contador_paginas')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="motivo_sin_lectura_id" class="form-label">Motivo sin lectura</label>
                <select id="motivo_sin_lectura_id" name="motivo_sin_lectura_id"
                        class="form-select @error('motivo_sin_lectura_id') is-invalid @enderror">
                    <option value="">Ninguno</option>
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo->id }}" @selected((int) old('motivo_sin_lectura_id') === $motivo->id)>
                            {{ ucfirst($motivo->nombre) }}
                        </option>
                    @endforeach
                </select>
                @error('motivo_sin_lectura_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="nivel_tipo_consumible_id" class="form-label">Tipo de nivel</label>
                <select id="nivel_tipo_consumible_id" name="nivel_tipo_consumible_id" class="form-select">
                    <option value="">Sin nivel</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}" @selected((int) old('nivel_tipo_consumible_id') === $tipo->id)>
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="nivel_color" class="form-label">Color</label>
                <select id="nivel_color" name="nivel_color" class="form-select">
                    @foreach (['negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica'] as $color)
                        <option value="{{ $color }}" @selected(old('nivel_color', 'no_aplica') === $color)>
                            {{ ucfirst(str_replace('_', ' ', $color)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="nivel" class="form-label">Nivel (0 a 1)</label>
                <input id="nivel" type="number" name="nivel" min="0" max="1" step="0.0001"
                       value="{{ old('nivel') }}" class="form-control"
                       placeholder="Ej. 0.75">
            </div>

            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"
                          class="form-control">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar lectura</button>
            <a href="{{ route('lecturas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
