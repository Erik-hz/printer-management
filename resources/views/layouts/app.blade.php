@extends('layouts.base')

@section('cuerpo')
    <div class="app-shell">
        <aside class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="appSidebar"
               aria-label="Navegación principal">
            <div class="offcanvas-header app-sidebar__mobile-header d-lg-none">
                <a class="app-brand" href="{{ route('inicio') }}">SGIIC<span>.</span></a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#appSidebar" aria-label="Cerrar menú"></button>
            </div>
            <div class="offcanvas-body app-sidebar__body">
                <a class="app-brand d-none d-lg-flex" href="{{ route('inicio') }}" aria-label="SGIIC, inicio">
                    SGIIC<span>.</span>
                </a>

                <nav class="app-nav">
                    <div class="app-nav__group">
                        <span class="app-nav__heading">General</span>
                        <a class="app-nav__link @if (request()->routeIs('inicio')) active @endif"
                           href="{{ route('inicio') }}" @if (request()->routeIs('inicio')) aria-current="page" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                            <span>Inicio</span>
                        </a>
                    </div>

                    @can('ver-impresoras')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Gestión de impresión</span>
                            @if (Route::has('impresoras.index'))
                                <a class="app-nav__link @if (request()->routeIs('impresoras.*')) active @endif"
                                   href="{{ route('impresoras.index') }}" @if (request()->routeIs('impresoras.*')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8V3h12v5M6 17H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/><path d="M18 11h.01"/></svg>
                                    <span>Impresoras</span>
                                </a>
                            @endif
                            @can('ver-monitoreo')
                                <a class="app-nav__link @if (request()->routeIs('lecturas.*')) active @endif"
                                   href="{{ route('lecturas.index') }}" @if (request()->routeIs('lecturas.*')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v18h18M7 14l4-4 4 3 6-7"/><path d="M17 6h4v4"/></svg>
                                    <span>Lecturas</span>
                                </a>
                            @endcan
                        </div>
                    @endcan

                    @can('ver-inventario')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Inventario</span>
                            <a class="app-nav__link @if (request()->routeIs('consumibles.*')) active @endif"
                               href="{{ route('consumibles.index') }}" @if (request()->routeIs('consumibles.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 7 9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10"/></svg>
                                <span>Consumibles</span>
                            </a>
                            <a class="app-nav__link @if (request()->routeIs('movimientos.*')) active @endif"
                               href="{{ route('movimientos.index') }}" @if (request()->routeIs('movimientos.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h14l-4-4M17 17H3l4 4M21 7l-4 4M3 17l4-4"/></svg>
                                <span>Movimientos</span>
                            </a>
                        </div>
                    @endcan

                    @can('gestionar-compras')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Abastecimiento</span>
                            <a class="app-nav__link @if (request()->routeIs('compras.*')) active @endif"
                               href="{{ route('compras.index') }}" @if (request()->routeIs('compras.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 9H6M10 21h.01M18 21h.01"/></svg>
                                <span>Órdenes de compra</span>
                            </a>
                            <a class="app-nav__link @if (request()->routeIs('proveedores.*')) active @endif"
                               href="{{ route('proveedores.index') }}" @if (request()->routeIs('proveedores.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 9h.01M15 9h.01M9 12h.01M15 12h.01"/></svg>
                                <span>Proveedores</span>
                            </a>
                            <a class="app-nav__link @if (request()->routeIs('precios-consumibles.*')) active @endif"
                               href="{{ route('precios-consumibles.index') }}" @if (request()->routeIs('precios-consumibles.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                <span>Precios de consumibles</span>
                            </a>
                        </div>
                    @endcan

                    @can('gestionar-importaciones')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Datos</span>
                            <a class="app-nav__link @if (request()->routeIs('importaciones.*')) active @endif"
                               href="{{ route('importaciones.index') }}" @if (request()->routeIs('importaciones.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V3m0 0L7 8m5-5 5 5M4 14v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6"/></svg>
                                <span>Importaciones</span>
                            </a>
                        </div>
                    @endcan

                    @can('ver-impresoras')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Catálogos</span>
                            <a class="app-nav__link @if (request()->routeIs('zonas.*', 'lugares.*', 'areas.*')) active @endif"
                               href="{{ route('zonas.index') }}" @if (request()->routeIs('zonas.*', 'lugares.*', 'areas.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18M3 12h18M3 19h18M8 3v4m8-4v4M8 10v4m8-4v4M8 17v4m8-4v4"/></svg>
                                <span>Ubicaciones</span>
                            </a>
                            <a class="app-nav__link @if (request()->routeIs('marcas.*')) active @endif"
                               href="{{ route('marcas.index') }}" @if (request()->routeIs('marcas.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg>
                                <span>Marcas</span>
                            </a>
                            <a class="app-nav__link @if (request()->routeIs('modelos.*')) active @endif"
                               href="{{ route('modelos.index') }}" @if (request()->routeIs('modelos.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                <span>Modelos de impresora</span>
                            </a>
                        </div>
                    @endcan

                    @can('gestionar-usuarios')
                        <div class="app-nav__group">
                            <span class="app-nav__heading">Administración</span>
                            <a class="app-nav__link @if (request()->routeIs('usuarios.*')) active @endif"
                               href="{{ route('usuarios.index') }}" @if (request()->routeIs('usuarios.*')) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6m3-3h-6"/></svg>
                                <span>Usuarios</span>
                            </a>
                        </div>
                    @endcan
                </nav>

                <div class="app-sidebar__account">
                    <div class="app-sidebar__avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                    <div class="app-sidebar__account-info">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ ucfirst(str_replace('_', ' ', auth()->user()->rol->nombre)) }}</span>
                    </div>
                    <div class="dropdown ms-auto">
                        <button class="app-sidebar__account-menu" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="Opciones de cuenta">⋮</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('perfil.clave') }}">Cambiar contraseña</a></li>
                            <li><hr class="dropdown-divider"></li>
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
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button class="btn app-menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Abrir menú">
                    <span></span><span></span><span></span>
                </button>
                <div class="app-breadcrumb">
                    <a href="{{ route('inicio') }}">Inicio</a>
                    <span aria-hidden="true">›</span>
                    <strong>@yield('titulo', 'Gestión de impresoras')</strong>
                </div>
                <div class="app-topbar__user">
                    <span>{{ auth()->user()->name }}</span>
                    <span class="app-topbar__user-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                </div>
            </header>

            <main class="app-content">
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
        </div>
    </div>
@endsection
