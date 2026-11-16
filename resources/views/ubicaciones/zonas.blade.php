@extends('layouts.app')

@section('titulo', 'Zonas')

@section('contenido')
    <h1 class="h3 mb-3">Ubicaciones: zonas</h1>

    @include('catalogos._nuevo', [
        'accion' => route('zonas.store'),
        'etiqueta' => 'Nueva zona',
        'max' => 100,
        'campoActivo' => 'activa',
    ])

    @include('catalogos._tabla', [
        'items' => $zonas,
        'max' => 100,
        'campoActivo' => 'activa',
        'etiquetaConteo' => 'Lugares',
        'campoConteo' => 'lugares_count',
        'etiquetaHijos' => 'Ver lugares',
        'rutas' => ['actualizar' => 'zonas.update', 'eliminar' => 'zonas.destroy', 'hijos' => 'lugares.index'],
    ])
@endsection