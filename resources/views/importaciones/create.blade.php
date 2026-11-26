@extends('layouts.app')

@section('titulo', 'Analizar Excel')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('importaciones.index') }}">Importaciones</a></li>
            <li class="breadcrumb-item active">Analizar Excel</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Analizar Excel</h1>
    <div class="alert alert-info" id="ayuda-lecturas">
        Se aceptan libros con el formato histórico usado en <strong>29052025</strong> y las variantes de
        <strong>insumos6-10.xlsx</strong>. La hoja se identifica por sus encabezados, no por el nombre de la pestaña.
        Se importan lecturas de porcentaje; los consumos anuales y los códigos de tóner no se cargan como inventario.
        La impresora debe existir previamente y coincidir por nombre o IP.
    </div>

    <form method="POST" action="{{ route('importaciones.analizar') }}" enctype="multipart/form-data" class="card card-body">
        @csrf
        <div class="mb-3">
            <label for="tipo_importacion" class="form-label">Tipo de importación</label>
            <select id="tipo_importacion" name="tipo_importacion" class="form-select mb-3">
                <option value="lecturas" @selected(old('tipo_importacion', 'lecturas') === 'lecturas')>Lecturas históricas de impresoras</option>
                <option value="consumibles" @selected(old('tipo_importacion') === 'consumibles')>Catálogo y stock inicial de consumibles</option>
            </select>
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
    <script>
        const selectorImportacion = document.getElementById('tipo_importacion');
        const ayudaLecturas = document.getElementById('ayuda-lecturas');
        const actualizarAyuda = () => {
            ayudaLecturas.hidden = selectorImportacion.value === 'consumibles';
        };
        selectorImportacion.addEventListener('change', actualizarAyuda);
        actualizarAyuda();
    </script>
@endsection
