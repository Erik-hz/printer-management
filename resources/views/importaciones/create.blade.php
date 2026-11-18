@extends('layouts.app')

@section('titulo', 'Analizar Excel histórico')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('importaciones.index') }}">Importaciones</a></li>
            <li class="breadcrumb-item active">Analizar Excel</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Analizar Excel histórico</h1>
    <div class="alert alert-info">
        Se leerá únicamente la hoja <strong>29052025</strong>. El porcentaje actual se registra como nivel histórico al
        31/03/2025; los valores <code>SR</code> y <code>DESC%</code> se registran como motivos al 29/05/2025.
        En equipos de color se importan los cuatro niveles en orden negro, cian, magenta y amarillo. Las cifras
        numéricas de consumo se omiten.
    </div>

    <form method="POST" action="{{ route('importaciones.analizar') }}" enctype="multipart/form-data" class="card card-body">
        @csrf
        <div class="mb-3">
            <label for="archivo" class="form-label">Libro de Excel (.xlsx, máximo 10 MB)</label>
            <input id="archivo" type="file" name="archivo" accept=".xlsx"
                   class="form-control @error('archivo') is-invalid @enderror" required>
            @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Analizar y mostrar vista previa</button>
            <a href="{{ route('importaciones.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
