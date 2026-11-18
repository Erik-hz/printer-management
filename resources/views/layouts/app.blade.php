@extends('layouts.base')

@section('cuerpo')
    <nav class="navbar navbar-expand-lg bg-dark border-bottom border-body" data-bs-theme="dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('inicio') }}">SGIIC</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Mostrar menú">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('inicio')) active @endif"
                           href="{{ route('inicio') }}">Inicio</a>
                    </li>
                    @if (Route::has('impresoras.index'))
                        @can('ver-impresoras')
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('impresoras.*')) active @endif"
                                   href="{{ route('impresoras.index') }}">Impresoras</a>
                            </li>
                        @endcan
                    @endif
                    @can('ver-inventario')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('consumibles.*')) active @endif"
                               href="{{ route('consumibles.index') }}">Consumibles</a>
                        </li>
                    @endcan
                    @can('ver-inventario')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('movimientos.*')) active @endif"
                               href="{{ route('movimientos.index') }}">Movimientos</a>
                        </li>
                    @endcan
                    @can('ver-monitoreo')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('lecturas.*')) active @endif"
                               href="{{ route('lecturas.index') }}">Lecturas</a>
                        </li>
                    @endcan
                    @can('gestionar-compras')
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle @if (request()->routeIs('compras.*', 'proveedores.*', 'precios-consumibles.*')) active @endif"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Compras</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('compras.index') }}">Órdenes de compra</a></li>
                                <li><a class="dropdown-item" href="{{ route('proveedores.index') }}">Proveedores</a></li>
                                <li><a class="dropdown-item" href="{{ route('precios-consumibles.index') }}">Precios de consumibles</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('gestionar-importaciones')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('importaciones.*')) active @endif"
                               href="{{ route('importaciones.index') }}">Importaciones</a>
                        </li>
                    @endcan
                    @can('ver-impresoras')
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle @if (request()->routeIs('zonas.*', 'lugares.*', 'areas.*', 'marcas.*', 'modelos.*')) active @endif"
                               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Catálogos</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('zonas.index') }}">Ubicaciones</a></li>
                                <li><a class="dropdown-item" href="{{ route('marcas.index') }}">Marcas</a></li>
                                <li><a class="dropdown-item" href="{{ route('modelos.index') }}">Modelos de impresora</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('gestionar-usuarios')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('usuarios.*')) active @endif"
                               href="{{ route('usuarios.index') }}">Usuarios</a>
                        </li>
                    @endcan
                </ul>

                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-body-secondary small">
                                Rol: {{ ucfirst(str_replace('_', ' ', auth()->user()->rol->nombre)) }}
                            </span>
                        </li>
                        <li><li><a class="dropdown-item" href="{{ route('perfil.clave') }}">Cambiar contraseña</a></li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @if (session('estado'))
            <div class="alert alert-success" role="alert">{{ session('estado') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('contenido')
    </main>
@endsection