<?php

namespace App\Modules\Imports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Imports\Http\Requests\ArchivoImportacionRequest;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Imports\Services\ImportacionExcelService;
use App\Modules\Imports\Services\ImportacionConsumiblesExcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ImportacionController extends Controller
{
    private const SESION_PREVIA = 'importacion_excel_previa';

    public function __construct(
        private readonly ImportacionExcelService $importaciones,
        private readonly ImportacionConsumiblesExcelService $importacionConsumibles,
    ) {}

    public function index(): View
    {
        return view('importaciones.index', [
            'importaciones' => Importacion::query()
                ->with('usuario')
                ->withCount('errores')
                ->orderByDesc('iniciada_en')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('importaciones.create');
    }

    public function analizar(ArchivoImportacionRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $tipoImportacion = $request->validated()['tipo_importacion'] ?? 'lecturas';
        $analisis = $tipoImportacion === 'consumibles'
            ? $this->importacionConsumibles->analizar($archivo->getRealPath())
            : $this->importaciones->analizar($archivo->getRealPath());
        $token = (string) Str::uuid();
        $nombre = basename(str_replace('\\', '/', $archivo->getClientOriginalName()));
        $nombre = Str::limit($nombre, 255, '');
        $ruta = "importaciones/pendientes/{$token}.json";
        $contenido = json_encode([
            'archivo_nombre' => $nombre,
            'analisis' => $analisis,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (! Storage::disk('local')->put($ruta, $contenido)) {
            throw new RuntimeException('No se pudo guardar la vista previa de la importación.');
        }

        $this->borrarVistaPreviaActual();
        session()->put(self::SESION_PREVIA, $token);

        return redirect()->route('importaciones.vista-previa');
    }

    public function vistaPrevia(): View|RedirectResponse
    {
        $ruta = $this->rutaVistaPrevia();

        if ($ruta === null || ! Storage::disk('local')->exists($ruta)) {
            $this->borrarVistaPreviaActual();

            return redirect()->route('importaciones.create')
                ->withErrors(['archivo' => 'La vista previa venció. Seleccione de nuevo el archivo.']);
        }

        $datos = json_decode(Storage::disk('local')->get($ruta), true, flags: JSON_THROW_ON_ERROR);

        return view('importaciones.preview', [
            'archivoNombre' => $datos['archivo_nombre'],
            'analisis' => $datos['analisis'],
        ]);
    }

    public function ejecutar(): RedirectResponse
    {
        $ruta = $this->rutaVistaPrevia();

        if ($ruta === null || ! Storage::disk('local')->exists($ruta)) {
            $this->borrarVistaPreviaActual();

            return redirect()->route('importaciones.create')
                ->withErrors(['archivo' => 'La vista previa venció. Analice de nuevo el archivo.']);
        }

        $datos = json_decode(Storage::disk('local')->get($ruta), true, flags: JSON_THROW_ON_ERROR);
        $servicio = ($datos['analisis']['formato'] ?? null) === 'inventario-consumibles'
            ? $this->importacionConsumibles
            : $this->importaciones;
        $importacion = $servicio->ejecutar(
            $datos['analisis'],
            $datos['archivo_nombre'],
            (int) request()->user()->id,
        );
        $this->borrarVistaPreviaActual();

        return redirect()->route('importaciones.show', $importacion);
    }

    public function cancelar(): RedirectResponse
    {
        $this->borrarVistaPreviaActual();

        return redirect()->route('importaciones.create')->with('estado', 'Vista previa descartada.');
    }

    public function show(Importacion $importacion): View
    {
        $importacion->load(['usuario', 'errores' => fn ($consulta) => $consulta->orderBy('fila')->orderBy('id')]);

        return view('importaciones.show', ['importacion' => $importacion]);
    }

    private function rutaVistaPrevia(): ?string
    {
        $token = session(self::SESION_PREVIA);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        return "importaciones/pendientes/{$token}.json";
    }

    private function borrarVistaPreviaActual(): void
    {
        $ruta = $this->rutaVistaPrevia();

        if ($ruta !== null) {
            Storage::disk('local')->delete($ruta);
        }

        session()->forget(self::SESION_PREVIA);
    }
}
