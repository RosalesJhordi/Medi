<?php

use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    /*
    |--------------------------------------------------------------------------
    | MEDIDAS
    |--------------------------------------------------------------------------
    */

    public float $ancho = 300;
    public float $alto = 220;
    public bool $conSobreluz = true;
    public float $puente = 130;

    public int $corredizas = 1;
    public int $fijos = 2;

    /*
    |--------------------------------------------------------------------------
    | OPCIONES DEL PLANO (antes eran inputs "sueltos" leídos por JS)
    |--------------------------------------------------------------------------
    */

    public string $vidrio = 'doble';
    public bool $mosquitero = false;

    public bool $mostrarCotas = true;
    public bool $mostrarCotaHoja = false;
    public bool $mostrarEtiquetas = true;

    /*
    |--------------------------------------------------------------------------
    | DATOS
    |--------------------------------------------------------------------------
    */

    public array $datos = [];
    public array $data = [];

    /*
    |--------------------------------------------------------------------------
    | MAMPARAS
    |--------------------------------------------------------------------------
    */

    public array $mamparas = [];
    public int $mamparaActiva = 0;

    /*
    |--------------------------------------------------------------------------
    | DESCUENTO INICIAL
    |--------------------------------------------------------------------------
    |
    | Este descuento se aplica ANTES de cualquier otro descuento.
    |
    | Ancho ingresado - 0.5
    | Alto ingresado  - 0.5
    |
    */

    private const DESCUENTO_INICIAL = 0.5;

    /*
    |--------------------------------------------------------------------------
    | CONSTANTES DEL PLANO (antes vivían dentro del <script>)
    |--------------------------------------------------------------------------
    */

    private const VIEW_W = 1100;
    private const VIEW_H = 760;

    private const MARGIN_LEFT = 160;
    private const MARGIN_RIGHT = 150;
    private const MARGIN_TOP = 90;
    private const MARGIN_BOTTOM = 190;

    private const PERFIL_MARCO_MM = 45;
    private const PERFIL_HOJA_MM = 40;
    private const OVERLAP_MM = 20;

    private const VIDRIO_LABELS = [
        'simple' => 'Vidrio simple 6mm',
        'doble' => 'Vidrio templado 6mm',
        'dvh' => 'DVH 6+12+6mm',
    ];

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->procesarPerfiles();

        $guardadas = session('mamparas');

        if (is_array($guardadas) && count($guardadas) > 0) {
            $this->mamparas = $guardadas;
            $this->mamparaActiva = 0;

            $this->cargarMamparaActiva();
        } else {
            $this->mamparas = [array_merge(['nombre' => 'M - 1'], $this->snapshotMampara())];

            $this->mamparaActiva = 0;

            session()->put('mamparas', $this->mamparas);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MEDIDA DE FABRICACIÓN
    |--------------------------------------------------------------------------
    */

    public function getAnchoFabricacionProperty(): float
    {
        return max(0, (float) $this->ancho - self::DESCUENTO_INICIAL);
    }

    public function getAltoFabricacionProperty(): float
    {
        return max(0, (float) $this->alto - self::DESCUENTO_INICIAL);
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL HOJAS
    |--------------------------------------------------------------------------
    */

    public function getTotalHojasProperty(): int
    {
        return max(0, (int) $this->fijos) + max(0, (int) $this->corredizas);
    }

    /*
    |--------------------------------------------------------------------------
    | ALTURA DEL SOBRELUZ
    |--------------------------------------------------------------------------
    |
    | "puente" representa la altura de la banda PRINCIPAL (donde están
    | las hojas), medida desde abajo. El sobreluz se lleva lo que sobra:
    |
    | Ej: alto = 250, puente = 200  →  sobreluz = 250 - 200 = 50 cm
    |
    */

    public function getSobreluzAlturaProperty(): float
    {
        if (!$this->conSobreluz || $this->puente <= 0) {
            return 0;
        }

        return max(0, $this->altoFabricacion - $this->puente);
    }

    /*
    |--------------------------------------------------------------------------
    | ALTO BANDA FIJA / CORREDIZA
    |--------------------------------------------------------------------------
    */

    public function getAltoBandaFijaProperty(): float
    {
        if ($this->fijos <= 0) {
            return 0;
        }

        if ($this->conSobreluz && $this->puente > 0) {
            return min($this->altoFabricacion, max(0, $this->puente));
        }

        return $this->altoFabricacion;
    }

    public function getAltoBandaCorredizaProperty(): float
    {
        if ($this->corredizas <= 0) {
            return 0;
        }

        if ($this->conSobreluz && $this->puente > 0) {
            return min($this->altoFabricacion, max(0, $this->puente));
        }

        return $this->altoFabricacion;
    }

    /*
    |--------------------------------------------------------------------------
    | CORTES DE VIDRIO
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    |
    | 1. Primero se aplica -0.5 al ancho y alto total.
    | 2. Después se aplican los descuentos propios de mampara.
    |
    */

    public function getCortesVidrioProperty(): array
    {
        $cortes = [];

        $W = $this->anchoFabricacion;

        // El ancho base se reparte entre el TOTAL de hojas (fijos + corredizas),
        // no entre las hojas de cada tipo por separado.
        $totalHojas = max(1, $this->totalHojas);
        $anchoBase = $W / $totalHojas;

        if ($this->fijos > 0) {
            $anchoFijo = max(0, $anchoBase - 4);
            $altoFijo = max(0, $this->altoBandaFija - 5.5);

            for ($i = 1; $i <= $this->fijos; $i++) {
                $cortes[] = [
                    'hoja' => 'Fijo ' . $i,
                    'tipo' => 'Fijo',
                    'ancho' => $anchoFijo,
                    'alto' => $altoFijo,
                ];
            }
        }

        if ($this->corredizas > 0) {
            $anchoCorrediza = max(0, $anchoBase + 4);
            $altoCorrediza = max(0, $this->altoBandaCorrediza - 6.5);

            for ($i = 1; $i <= $this->corredizas; $i++) {
                $cortes[] = [
                    'hoja' => 'Corredizo ' . $i,
                    'tipo' => 'Corredizo',
                    'ancho' => $anchoCorrediza,
                    'alto' => $altoCorrediza,
                ];
            }
        }

        $sobreluzAltura = $this->sobreluzAltura;

        if ($sobreluzAltura > 0) {
            $anchoSobreluz = max(0, $W - 4);
            $altoSobreluz = max(0, $sobreluzAltura - 5.5);

            $cortes[] = [
                'hoja' => 'Sobreluz',
                'tipo' => 'Fijo',
                'ancho' => $anchoSobreluz,
                'alto' => $altoSobreluz,
            ];
        }

        return $cortes;
    }

    /*
    |--------------------------------------------------------------------------
    | DISTRIBUCIÓN DE HOJAS
    |--------------------------------------------------------------------------
    */

    public function getDistribucionHojasProperty(): array
    {
        $fijosIzquierda = (int) ceil($this->fijos / 2);
        $fijosDerecha = $this->fijos - $fijosIzquierda;

        $tipos = [];

        for ($i = 0; $i < $fijosIzquierda; $i++) {
            $tipos[] = 'F';
        }

        for ($i = 0; $i < $this->corredizas; $i++) {
            $tipos[] = 'C';
        }

        for ($i = 0; $i < $fijosDerecha; $i++) {
            $tipos[] = 'F';
        }

        return $tipos ?: ['F'];
    }

    /*
    |--------------------------------------------------------------------------
    | PERFILES
    |--------------------------------------------------------------------------
    */

    public function getPerfilesMamparaProperty(): array
    {
        $W = $this->anchoFabricacion;
        $P = $this->sobreluzAltura;

        return [['codigo' => '7955', 'nombre' => 'U de 3/4', 'medida' => $W, 'cant' => 1], ['codigo' => '5283', 'nombre' => 'Tubo multifuncional', 'medida' => $W, 'cant' => 1], ['codigo' => '8413', 'nombre' => 'Riel baja', 'medida' => $W, 'cant' => 1], ['codigo' => '3003', 'nombre' => 'U de 1/2', 'medida' => $P / 2 + 4.2, 'cant' => max(0, $this->fijos)], ['codigo' => '4104', 'nombre' => 'Ángulo de 1/2 económico', 'medida' => max(0, $P - 0.3), 'cant' => max(0, $this->fijos)], ['codigo' => '8220', 'nombre' => 'H 6 mm', 'medida' => $P / 2 + 3.6, 'cant' => max(0, $this->corredizas)]];
    }

    /*
    |--------------------------------------------------------------------------
    | GEOMETRÍA DEL PLANO (reemplaza toda la lógica que antes vivía en JS)
    |--------------------------------------------------------------------------
    */

    public function getPlanoProperty(): array
    {
        $Wcm = max(0, (float) $this->ancho);
        $Hcm = max(0, (float) $this->alto);
        $SobreluzAltura = $this->sobreluzAltura;

        $nFijos = max(0, (int) $this->fijos);
        $nCorredizas = max(0, (int) $this->corredizas);
        $numHojas = max(1, $nFijos + $nCorredizas);

        $anchoFabricacion = $this->anchoFabricacion;
        $altoFabricacion = $this->altoFabricacion;

        $drawW = self::VIEW_W - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $drawH = self::VIEW_H - self::MARGIN_TOP - self::MARGIN_BOTTOM;

        $Wmm = max(1, $anchoFabricacion * 10);
        $Hmm = max(1, $altoFabricacion * 10);

        $scale = min($drawW / $Wmm, $drawH / $Hmm);
        $escala = max(1, round((1 / $scale) * 10) / 10);

        $w = $Wmm * $scale;
        $h = $Hmm * $scale;

        $x0 = self::MARGIN_LEFT + ($drawW - $w) / 2;
        $y0 = self::MARGIN_TOP + ($drawH - $h) / 2;

        $fm = self::PERFIL_MARCO_MM * $scale;
        $fh = self::PERFIL_HOJA_MM * $scale;
        $ov = self::OVERLAP_MM * $scale;

        $innerX = $x0 + $fm;
        $innerY = $y0 + $fm;
        $innerW = max(2, $w - $fm * 2);
        $innerH = max(2, $h - $fm * 2);

        $haySobreluz = $this->conSobreluz && $SobreluzAltura > 0;
        $puentePx = min($innerH * 0.75, $SobreluzAltura * 10 * $scale);
        $travesanoPx = $haySobreluz ? max(5, $fm * 0.6) : 0;
        $sobreluzH = $haySobreluz ? max(5, $puentePx) : 0;

        $filaY = $innerY + $sobreluzH + $travesanoPx;
        $filaH = max(10, $innerH - $sobreluzH - $travesanoPx);

        $filaTipos = $this->distribucionHojas;
        $n = count($filaTipos);
        $wRaw = $innerW / max(1, $n);

        // Ancho real de cada hoja: el ancho de fabricación se reparte entre
        // el TOTAL de hojas (fijos + corredizas), y luego se le suma 4 cm si
        // es corrediza o se le resta 4 cm si es fija. Misma base que usa
        // getCortesVidrioProperty() para la tabla de corte de vidrios.
        $anchoBaseCota = $anchoFabricacion / max(1, $n);
        $anchoFijoCota = max(0, $anchoBaseCota - 4);
        $anchoCorredizaCota = max(0, $anchoBaseCota + 4);

        $panels = [];

        foreach ($filaTipos as $i => $tipo) {
            $esCorrediza = $tipo === 'C';

            $leftOverlap = $esCorrediza && $i > 0 ? $ov / 2 : 0;
            $rightOverlap = $esCorrediza && $i < $n - 1 ? $ov / 2 : 0;

            $hx = $innerX + $i * $wRaw - $leftOverlap;
            $hw = $wRaw + $leftOverlap + $rightOverlap;

            $gx = $hx + $fh;
            $gy = $filaY + $fh;
            $gw = max(2, $hw - $fh * 2);
            $gh = max(2, $filaH - $fh * 2);

            $panels[] = [
                'esCorrediza' => $esCorrediza,
                'hx' => $hx,
                'hw' => max($hw, 3),
                'gx' => $gx,
                'gy' => $gy,
                'gw' => $gw,
                'gh' => $gh,
                'handleX' => $hx + $hw - 12,
                'handleY' => $filaY + $filaH / 2 - 15,
                'cotaLabel' => number_format($esCorrediza ? $anchoCorredizaCota : $anchoFijoCota, 1) . ' cm',
            ];
        }

        $railY1 = null;
        $railY2 = null;

        if ($nCorredizas > 0) {
            $railY1 = $filaY + $filaH - max(4, $fm * 0.35);
            $railY2 = $filaY + $filaH - max(7, $fm * 0.65);
        }

        $areaCm2 = 0;

        foreach ($this->cortesVidrio as $c) {
            $areaCm2 += max(0, $c['ancho']) * max(0, $c['alto']);
        }

        $areaM2 = $areaCm2 / 10000;
        $perimM = (($Wcm + $Hcm) * 2) / 100;

        return [
            'Wcm' => $Wcm,
            'Hcm' => $Hcm,
            'Puente' => $SobreluzAltura,
            'ConSobreluz' => $haySobreluz,
            'nFijos' => $nFijos,
            'nCorredizas' => $nCorredizas,
            'numHojas' => $numHojas,
            'anchoFabricacion' => $anchoFabricacion,
            'altoFabricacion' => $altoFabricacion,
            'escala' => $escala,
            'w' => $w,
            'h' => $h,
            'x0' => $x0,
            'y0' => $y0,
            'fm' => $fm,
            'fh' => $fh,
            'innerX' => $innerX,
            'innerY' => $innerY,
            'innerW' => $innerW,
            'innerH' => $innerH,
            'haySobreluz' => $haySobreluz,
            'sobreluzH' => $sobreluzH,
            'travesanoPx' => $travesanoPx,
            'filaY' => $filaY,
            'filaH' => $filaH,
            'panels' => $panels,
            'railY1' => $railY1,
            'railY2' => $railY2,
            'areaM2' => $areaM2,
            'perimM' => $perimM,
            'vidrioLabel' => self::VIDRIO_LABELS[$this->vidrio] ?? 'Vidrio 6mm',
            'fecha' => now()->translatedFormat('d/m/Y'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SNAPSHOT / GUARDADO / CARGA DE MAMPARAS
    |--------------------------------------------------------------------------
    */

    private function snapshotMampara(): array
    {
        return [
            'ancho' => (float) $this->ancho,
            'alto' => (float) $this->alto,
            'conSobreluz' => $this->conSobreluz,
            'puente' => (float) $this->puente,
            'corredizas' => max(0, (int) $this->corredizas),
            'fijos' => max(0, (int) $this->fijos),
            'vidrio' => $this->vidrio,
            'mosquitero' => $this->mosquitero,
            'mostrarCotas' => $this->mostrarCotas,
            'mostrarCotaHoja' => $this->mostrarCotaHoja,
            'mostrarEtiquetas' => $this->mostrarEtiquetas,
            'datos' => $this->datos,
        ];
    }

    private function guardarMamparaActual(): void
    {
        if (!isset($this->mamparas[$this->mamparaActiva])) {
            return;
        }

        $nombre = $this->mamparas[$this->mamparaActiva]['nombre'] ?? 'M - ' . ($this->mamparaActiva + 1);

        $this->mamparas[$this->mamparaActiva] = array_merge(['nombre' => $nombre], $this->snapshotMampara());

        session()->put('mamparas', $this->mamparas);
    }

    private function cargarMampara(int $i): void
    {
        if (!isset($this->mamparas[$i])) {
            return;
        }

        $p = $this->mamparas[$i];

        $this->ancho = (float) ($p['ancho'] ?? 300);
        $this->alto = (float) ($p['alto'] ?? 220);
        $this->conSobreluz = (bool) ($p['conSobreluz'] ?? true);
        $this->puente = (float) ($p['puente'] ?? 130);

        $this->corredizas = max(0, (int) ($p['corredizas'] ?? 1));
        $this->fijos = max(0, (int) ($p['fijos'] ?? 2));

        $this->vidrio = $p['vidrio'] ?? 'doble';
        $this->mosquitero = (bool) ($p['mosquitero'] ?? false);

        $this->mostrarCotas = (bool) ($p['mostrarCotas'] ?? true);
        $this->mostrarCotaHoja = (bool) ($p['mostrarCotaHoja'] ?? false);
        $this->mostrarEtiquetas = (bool) ($p['mostrarEtiquetas'] ?? true);

        $this->datos = $p['datos'] ?? [];
    }

    public function cambiarMampara(int $i): void
    {
        if (!isset($this->mamparas[$i])) {
            return;
        }

        $this->guardarMamparaActual();

        $this->mamparaActiva = $i;

        $this->cargarMamparaActiva();
    }

    private function cargarMamparaActiva(): void
    {
        if (!isset($this->mamparas[$this->mamparaActiva])) {
            return;
        }

        $this->cargarMampara($this->mamparaActiva);
    }

    public function agregarMampara(): void
    {
        $this->guardarMamparaActual();

        $this->ancho = 300;
        $this->alto = 220;
        $this->conSobreluz = true;
        $this->puente = 130;

        $this->corredizas = 1;
        $this->fijos = 2;

        $this->vidrio = 'doble';
        $this->mosquitero = false;
        $this->mostrarCotas = true;
        $this->mostrarCotaHoja = false;
        $this->mostrarEtiquetas = true;

        $this->datos = [];

        $this->mamparas[] = array_merge(['nombre' => 'M - ' . (count($this->mamparas) + 1)], $this->snapshotMampara());

        $this->mamparaActiva = count($this->mamparas) - 1;

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('correcto', 'Vista agregada con exito');
    }

    public function eliminarMampara(int $index): void
    {
        if (count($this->mamparas) <= 1) {
            return;
        }

        if (!isset($this->mamparas[$index])) {
            return;
        }

        unset($this->mamparas[$index]);

        $this->mamparas = array_values($this->mamparas);

        if ($this->mamparaActiva >= count($this->mamparas)) {
            $this->mamparaActiva = count($this->mamparas) - 1;
        }

        $this->cargarMamparaActiva();

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('delete', 'Vista eliminada con exito');
    }

    private function resetMamparas(): void
    {
        $this->mamparas = [
            array_merge(
                ['nombre' => 'M - 1'],
                [
                    'ancho' => 300,
                    'alto' => 220,
                    'conSobreluz' => true,
                    'puente' => 130,
                    'corredizas' => 1,
                    'fijos' => 2,
                    'vidrio' => 'doble',
                    'mosquitero' => false,
                    'mostrarCotas' => true,
                    'mostrarCotaHoja' => false,
                    'mostrarEtiquetas' => true,
                    'datos' => [],
                ],
            ),
        ];

        $this->mamparaActiva = 0;

        $this->cargarMamparaActiva();

        session()->put('mamparas', $this->mamparas);
    }

    /*
    |--------------------------------------------------------------------------
    | EXCEL
    |--------------------------------------------------------------------------
    */

    public function procesarPerfiles(): void
    {
        $ruta = public_path('datos.xlsx');

        if (!file_exists($ruta)) {
            $this->data = [];
            return;
        }

        try {
            $hojas = Excel::toArray([], $ruta);

            $this->data = collect($hojas[0] ?? [])
                ->pluck(0)
                ->filter()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $this->data = [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IMPRIMIR / LIMPIAR
    |--------------------------------------------------------------------------
    */

    public function imprimirTodo(): void
    {
        $this->guardarMamparaActual();

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('imprimir-mamparas');
    }

    protected $listeners = ['limpiar-mamparas' => 'limpiarMamparas'];

    public function limpiarMamparas(): void
    {
        session()->forget('mamparas');

        $this->resetMamparas();

        $this->dispatch('correcto', 'Mamparas limpiadas correctamente');
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDADO AUTOMÁTICO
    |--------------------------------------------------------------------------
    */

    public function updated(string $property): void
    {
        if (in_array($property, ['ancho', 'alto', 'conSobreluz', 'puente', 'corredizas', 'fijos', 'datos', 'vidrio', 'mosquitero', 'mostrarCotas', 'mostrarCotaHoja', 'mostrarEtiquetas'], true)) {
            $this->guardarMamparaActual();
        }
    }
};
?>

@php
    $tarjeta = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    $inputClase =
        'w-full rounded-xl border border-slate-300 bg-white pl-3 pr-10 py-2.5 text-sm font-semibold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none';
    $opciones = [
        ['mosquitero', 'Mosquitero', 'fa-border-none'],
        ['mostrarCotas', 'Cotas generales', 'fa-ruler-combined'],
        ['mostrarCotaHoja', 'Cota por hoja', 'fa-arrows-left-right'],
        ['mostrarEtiquetas', 'Etiquetas de partes', 'fa-tags'],
    ];
    $pl = $this->plano;
@endphp

<div class="mx-auto max-w-7xl p-3 font-sans text-slate-800 md:p-6">

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    {{-- LOADING --}}
    <div wire:loading class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm">
        <div class="absolute inset-0 flex items-center justify-center">
            <img src="{{ asset('img/tape.gif') }}" alt=""
                class="h-32 w-32 rounded-full shadow-2xl ring-4 ring-white">
        </div>
    </div>

    {{-- ENCABEZADO --}}
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-indigo-500">Calculadora de mamparas</p>
            <h1 class="text-3xl font-black tracking-tight text-slate-800">
                {{ $mamparas[$mamparaActiva]['nombre'] ?? '—' }}
                <span class="ml-1 text-base font-semibold text-slate-400">Sistema corredizo</span>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="imprimirTodo"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                <i class="fa-solid fa-print"></i> Imprimir plano
            </button>
            <button type="button" wire:click="agregarMampara"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 active:scale-95">
                <i class="fa-solid fa-plus"></i> Nueva mampara
            </button>
            <button type="button" wire:click="limpiarMamparas" wire:confirm="¿Vaciar todas las mamparas?"
                class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-bold text-red-600 transition hover:bg-red-50 active:scale-95">
                <i class="fa-solid fa-trash"></i> Vaciar
            </button>
        </div>
    </div>

    {{-- PESTAÑAS --}}
    <div class="mb-5 flex items-center gap-2 overflow-x-auto pb-1">
        @foreach ($mamparas as $index => $v)
            <div class="group relative shrink-0" wire:key="mampara-tab-{{ $index }}">
                <button type="button" wire:click="cambiarMampara({{ $index }})"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2 text-xs font-extrabold uppercase tracking-wide transition
                    {{ $mamparaActiva == $index
                        ? 'bg-slate-800 text-white shadow-md'
                        : 'bg-white text-slate-500 ring-1 ring-slate-200 hover:text-indigo-600 hover:ring-indigo-300' }}">
                    <i class="fa-solid fa-table-cells-large"></i> {{ $v['nombre'] }}
                </button>

                @if (count($mamparas) > 1)
                    <button type="button" wire:click.stop="eliminarMampara({{ $index }})" title="Eliminar"
                        class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow transition lg:opacity-0 lg:group-hover:opacity-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- RESUMEN --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="{{ $tarjeta }} p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Medida ingresada</p>
            <p class="mt-1 font-mono text-xl font-bold text-slate-800">
                {{ number_format($ancho, 1) }} × {{ number_format($alto, 1) }}
                <span class="text-xs font-semibold text-slate-400">cm</span>
            </p>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Medida fabricación</p>
            <p class="mt-1 font-mono text-xl font-bold text-indigo-700">
                {{ number_format($this->anchoFabricacion, 1) }} × {{ number_format($this->altoFabricacion, 1) }}
                <span class="text-xs font-semibold">cm</span>
            </p>
            <p class="text-[10px] font-semibold text-indigo-400">Descuento inicial −0.5 × −0.5 cm</p>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-400">Área de vidrio</p>
            <p class="mt-1 font-mono text-xl font-bold text-sky-700">
                {{ number_format($pl['areaM2'], 2) }} <span class="text-xs font-semibold">m²</span>
            </p>
            <p class="text-[10px] font-semibold text-sky-400">Perímetro {{ number_format($pl['perimM'], 2) }} m</p>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Hojas</p>
            <p class="mt-1 font-mono text-xl font-bold text-emerald-700">
                {{ $this->totalHojas }} <span class="text-xs font-semibold">en total</span>
            </p>
            <p class="text-[10px] font-semibold text-emerald-500">{{ $fijos }} fijos · {{ $corredizas }}
                corredizas</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">

        {{-- ===================== COLUMNA IZQUIERDA ===================== --}}
        <div class="space-y-5 xl:col-span-4">

            {{-- MEDIDAS --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="mb-4 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <i class="fa-solid fa-ruler-combined text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Medidas</h2>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Ancho</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" inputmode="decimal"
                                wire:model.lazy="ancho" class="{{ $inputClase }}">
                            <span
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Alto</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" inputmode="decimal"
                                wire:model.lazy="alto" class="{{ $inputClase }}">
                            <span
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                        </div>
                    </div>
                </div>

                {{-- SOBRELUZ --}}
                <div class="mt-4 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
                    <label class="flex cursor-pointer select-none items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <i class="fa-solid fa-window-maximize text-slate-400"></i>
                            Con sobreluz
                        </span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" wire:model.live="conSobreluz" class="peer sr-only">
                            <span
                                class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-200"></span>
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    @if ($conSobreluz)
                        <div class="mt-3">
                            <label
                                class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Puente
                                (banda de hojas)</label>
                            <div class="relative">
                                <input type="number" step="0.1" min="0" inputmode="decimal"
                                    wire:model.lazy="puente" class="{{ $inputClase }}">
                                <span
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                            </div>
                            <p
                                class="mt-2 rounded-lg bg-white px-3 py-2 font-mono text-[11px] leading-snug text-slate-500 ring-1 ring-slate-200">
                                Sobreluz = {{ number_format($this->altoFabricacion, 1) }} −
                                {{ number_format($puente, 1) }}
                                = <b class="text-indigo-600">{{ number_format($this->sobreluzAltura, 1) }} cm</b>
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COMPOSICIÓN --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="mb-4 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <i class="fa-solid fa-table-columns text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Composición</h2>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['corredizas', 'Corredizas', $corredizas], ['fijos', 'Fijos', $fijos]] as [$campo, $etiqueta, $valor])
                        <div>
                            <label
                                class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $etiqueta }}</label>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                    wire:click="$set('{{ $campo }}', {{ max(0, $valor - 1) }})"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <input type="number" min="0" inputmode="numeric"
                                    wire:model.lazy="{{ $campo }}"
                                    class="w-full min-w-0 rounded-xl border border-slate-300 bg-white px-2 py-2.5 text-center text-sm font-bold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none">
                                <button type="button" wire:click="$set('{{ $campo }}', {{ $valor + 1 }})"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Distribución --}}
                <div class="mt-4">
                    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">Distribución de la
                        fila</p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ($this->distribucionHojas as $tipo)
                            <span
                                class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg px-2 font-mono text-sm font-extrabold
                                {{ $tipo === 'C' ? 'bg-rose-100 text-rose-700 ring-1 ring-rose-200' : 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200' }}">
                                {{ $tipo }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        <b class="text-indigo-600">F</b> fijo · <b class="text-rose-600">C</b> corredizo
                        @if ($pl['haySobreluz'])
                            · sobreluz fijo arriba ({{ number_format($pl['Puente'], 1) }} cm)
                        @endif
                    </p>
                </div>

                {{-- Vidrio --}}
                <div class="mt-4">
                    <label
                        class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Vidrio</label>
                    <select wire:model.live="vidrio"
                        class="w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none">
                        <option value="simple">Simple 6mm</option>
                        <option value="doble">Templado 6mm</option>
                        <option value="dvh">DVH 6+12+6mm</option>
                    </select>
                </div>
            </div>

            {{-- VISTA Y OPCIONES --}}
            <div class="{{ $tarjeta }} p-3">
                <div class="mb-1 flex items-center gap-2 px-2 pt-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-sliders text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Vista y opciones</h2>
                </div>

                @foreach ($opciones as [$clave, $texto, $icono])
                    <label
                        class="flex cursor-pointer select-none items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-slate-50">
                        <span class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
                            <i class="fa-solid {{ $icono }} w-4 text-center text-slate-400"></i>
                            {{ $texto }}
                        </span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" wire:model.live="{{ $clave }}" class="peer sr-only">
                            <span
                                class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-200"></span>
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ===================== COLUMNA DERECHA: PLANO ===================== --}}
        <section x-data="{ mostrarPlano: true }" class="{{ $tarjeta }} h-fit overflow-hidden xl:col-span-8">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800 font-mono text-sm font-bold text-white">C</span>
                    <div>
                        <h2 class="text-base font-extrabold tracking-tight text-slate-800">Plano de mampara</h2>
                        <p class="text-[11px] text-slate-400">Elevación frontal · cotas en cm / mm</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 font-mono text-[11px] font-semibold text-slate-600">{{ $pl['fecha'] }}</span>
                    <span
                        class="rounded-full bg-indigo-50 px-3 py-1 font-mono text-[11px] font-semibold text-indigo-600">Escala
                        1:{{ $pl['escala'] }}</span>
                    <button type="button" @click="mostrarPlano = !mostrarPlano"
                        class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white transition hover:bg-slate-700 active:scale-95">
                        <i class="fa-solid" :class="mostrarPlano ? 'fa-eye-slash' : 'fa-eye'"></i>
                        <span x-text="mostrarPlano ? 'Ocultar' : 'Mostrar'"></span>
                    </button>
                </div>
            </div>

            <div x-show="mostrarPlano" x-cloak class="bg-[#F8FAFC] p-3 sm:p-4">
                <div class="flex w-full items-center justify-center overflow-x-auto">

                    @php
                        $p = $this->plano;

                        $ink = '#1E293B';
                        $dim = '#C2410C';
                        $acc = '#4F46E5';

                        $x0 = $p['x0'];
                        $y0 = $p['y0'];
                        $W = $p['w'];
                        $H = $p['h'];
                        $fm = $p['fm'];
                        $fh = $p['fh'];
                        $xR = $x0 + $W;
                        $yB = $y0 + $H;

                        // ancho de una "pastilla" de texto
                        $pw = fn($t, $fs = 6.2) => mb_strlen($t) * $fs + 18;

                        // COTA HORIZONTAL (extensiones + marcas oblicuas + pastilla)
                        $dimH = function ($xa, $xb, $y, $yRef, $txt, $fs = 12) use ($dim) {
                            $m = ($xa + $xb) / 2;
                            $w = mb_strlen($txt) * $fs * 0.6 + 18;
                            $e = $yRef > $y ? -6 : 6;
                            return '<g fill="none" stroke="' .
                                $dim .
                                '" stroke-width="1">' .
                                '<line x1="' .
                                $xa .
                                '" y1="' .
                                $yRef .
                                '" x2="' .
                                $xa .
                                '" y2="' .
                                ($y + $e) .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xb .
                                '" y1="' .
                                $yRef .
                                '" x2="' .
                                $xb .
                                '" y2="' .
                                ($y + $e) .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xa .
                                '" y1="' .
                                $y .
                                '" x2="' .
                                $xb .
                                '" y2="' .
                                $y .
                                '"/>' .
                                '<line x1="' .
                                ($xa - 5) .
                                '" y1="' .
                                ($y + 5) .
                                '" x2="' .
                                ($xa + 5) .
                                '" y2="' .
                                ($y - 5) .
                                '" stroke-width="1.8"/>' .
                                '<line x1="' .
                                ($xb - 5) .
                                '" y1="' .
                                ($y + 5) .
                                '" x2="' .
                                ($xb + 5) .
                                '" y2="' .
                                ($y - 5) .
                                '" stroke-width="1.8"/>' .
                                '</g>' .
                                '<rect x="' .
                                ($m - $w / 2) .
                                '" y="' .
                                ($y - 10) .
                                '" width="' .
                                $w .
                                '" height="20" rx="10" fill="#fff" stroke="' .
                                $dim .
                                '" stroke-width=".8"/>' .
                                '<text x="' .
                                $m .
                                '" y="' .
                                ($y + 4) .
                                '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' .
                                $fs .
                                '" font-weight="500" fill="' .
                                $dim .
                                '">' .
                                e($txt) .
                                '</text>';
                        };

                        // COTA VERTICAL
                        $dimV = function ($x, $ya, $yb, $xRef, $txt, $fs = 12) use ($dim) {
                            $m = ($ya + $yb) / 2;
                            $w = mb_strlen($txt) * $fs * 0.6 + 18;
                            $e = $xRef > $x ? -6 : 6;
                            return '<g fill="none" stroke="' .
                                $dim .
                                '" stroke-width="1">' .
                                '<line x1="' .
                                $xRef .
                                '" y1="' .
                                $ya .
                                '" x2="' .
                                ($x + $e) .
                                '" y2="' .
                                $ya .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xRef .
                                '" y1="' .
                                $yb .
                                '" x2="' .
                                ($x + $e) .
                                '" y2="' .
                                $yb .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $x .
                                '" y1="' .
                                $ya .
                                '" x2="' .
                                $x .
                                '" y2="' .
                                $yb .
                                '"/>' .
                                '<line x1="' .
                                ($x - 5) .
                                '" y1="' .
                                ($ya + 5) .
                                '" x2="' .
                                ($x + 5) .
                                '" y2="' .
                                ($ya - 5) .
                                '" stroke-width="1.8"/>' .
                                '<line x1="' .
                                ($x - 5) .
                                '" y1="' .
                                ($yb + 5) .
                                '" x2="' .
                                ($x + 5) .
                                '" y2="' .
                                ($yb - 5) .
                                '" stroke-width="1.8"/>' .
                                '</g>' .
                                '<g transform="rotate(-90 ' .
                                $x .
                                ' ' .
                                $m .
                                ')">' .
                                '<rect x="' .
                                ($x - $w / 2) .
                                '" y="' .
                                ($m - 10) .
                                '" width="' .
                                $w .
                                '" height="20" rx="10" fill="#fff" stroke="' .
                                $dim .
                                '" stroke-width=".8"/>' .
                                '<text x="' .
                                $x .
                                '" y="' .
                                ($m + 4) .
                                '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' .
                                $fs .
                                '" font-weight="500" fill="' .
                                $dim .
                                '">' .
                                e($txt) .
                                '</text>' .
                                '</g>';
                        };

                        // ETIQUETA CON LÍNEA GUÍA
                        // modo 'l' = pastilla a la izquierda del punto final (x,y)
                        // modo 't' = pastilla arriba, su borde izquierdo en x
                        // modo 'te' = pastilla arriba, su borde derecho en x
                        $call = function ($px, $py, $txt, $x, $y, $mode = 'l') use ($acc) {
                            $w = mb_strlen($txt) * 6.2 + 18;
                            if ($mode === 'l') {
                                $rx = $x - $w;
                                $ex = $x;
                                $ey = $y;
                            } elseif ($mode === 't') {
                                $rx = $x;
                                $ex = $x + $w / 2;
                                $ey = $y + 11;
                            } else {
                                $rx = $x - $w;
                                $ex = $x - $w / 2;
                                $ey = $y + 11;
                            }
                            return '<line x1="' .
                                $px .
                                '" y1="' .
                                $py .
                                '" x2="' .
                                $ex .
                                '" y2="' .
                                $ey .
                                '" stroke="' .
                                $acc .
                                '" stroke-width="1" stroke-opacity=".7"/>' .
                                '<circle cx="' .
                                $px .
                                '" cy="' .
                                $py .
                                '" r="3" fill="#fff" stroke="' .
                                $acc .
                                '" stroke-width="1.5"/>' .
                                '<rect x="' .
                                $rx .
                                '" y="' .
                                ($y - 11) .
                                '" width="' .
                                $w .
                                '" height="22" rx="11" fill="' .
                                $acc .
                                '"/>' .
                                '<text x="' .
                                ($rx + $w / 2) .
                                '" y="' .
                                ($y + 4) .
                                '" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#fff">' .
                                e($txt) .
                                '</text>';
                        };

                        // primer paño fijo y primera hoja corrediza (para anclar etiquetas)
                        $pFijo = collect($p['panels'])->first(fn($q) => !$q['esCorrediza']);
                        $pCorr = collect($p['panels'])->first(fn($q) => $q['esCorrediza']);

                        $bandaHojas = max(0, $p['altoFabricacion'] - ($p['haySobreluz'] ? $p['Puente'] : 0));
                    @endphp

                    <svg viewBox="0 0 1100 760" xmlns="http://www.w3.org/2000/svg" width="100%"
                        preserveAspectRatio="xMidYMid meet"
                        style="display:block;width:100%;height:auto;min-height:500px;">

                        <defs>
                            {{-- fondo tipo papel milimetrado --}}
                            <pattern id="gridSmall" width="20" height="20" patternUnits="userSpaceOnUse">
                                <path d="M20 0H0V20" fill="none" stroke="#CBD5E1" stroke-opacity=".35"
                                    stroke-width=".6" />
                            </pattern>

                            {{-- pared / piso --}}
                            <pattern id="hatchWall" width="8" height="8" patternUnits="userSpaceOnUse"
                                patternTransform="rotate(-45)">
                                <line x1="0" y1="0" x2="0" y2="8" stroke="#94A3B8"
                                    stroke-opacity=".55" stroke-width="1" />
                            </pattern>

                            {{-- aluminio --}}
                            <linearGradient id="alu" x1="0" y1="0" x2="1"
                                y2="1">
                                <stop offset="0" stop-color="#F1F5F9" />
                                <stop offset=".45" stop-color="#CBD5E1" />
                                <stop offset="1" stop-color="#94A3B8" />
                            </linearGradient>
                            <linearGradient id="aluLeaf" x1="0" y1="0" x2="0"
                                y2="1">
                                <stop offset="0" stop-color="#F8FAFC" />
                                <stop offset="1" stop-color="#B6C2D1" />
                            </linearGradient>

                            {{-- vidrio --}}
                            <linearGradient id="glass" x1="0" y1="0" x2="1"
                                y2="1">
                                <stop offset="0" stop-color="#DFF0FB" />
                                <stop offset=".55" stop-color="#BBDDF2" />
                                <stop offset="1" stop-color="#9CC9E6" />
                            </linearGradient>

                            {{-- malla del mosquitero --}}
                            <pattern id="mesh" width="5" height="5" patternUnits="userSpaceOnUse">
                                <path d="M0 0H5M0 0V5" stroke="#475569" stroke-opacity=".35" stroke-width=".5"
                                    fill="none" />
                            </pattern>

                            <marker id="slideArrow" markerWidth="10" markerHeight="10" refX="8"
                                refY="5" orient="auto">
                                <path d="M1 1 L8 5 L1 9 Z" fill="#334155" fill-opacity=".7" />
                            </marker>

                            <filter id="softShadow" x="-10%" y="-10%" width="120%" height="125%">
                                <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#0F172A"
                                    flood-opacity=".18" />
                            </filter>
                        </defs>

                        {{-- FONDO --}}
                        <rect width="1100" height="760" fill="#F8FAFC" />
                        <rect width="1100" height="760" fill="url(#gridSmall)" />

                        {{-- PARED (vano) Y PISO --}}
                        <rect x="{{ $x0 - 16 }}" y="{{ $y0 - 16 }}" width="{{ $W + 32 }}"
                            height="{{ $H + 16 }}" fill="#EEF2F6" />
                        <rect x="{{ $x0 - 16 }}" y="{{ $y0 - 16 }}" width="{{ $W + 32 }}"
                            height="{{ $H + 16 }}" fill="url(#hatchWall)" />
                        <rect x="{{ $x0 - 50 }}" y="{{ $yB }}" width="{{ $W + 100 }}"
                            height="8" fill="url(#hatchWall)" />
                        <line x1="{{ $x0 - 50 }}" y1="{{ $yB }}" x2="{{ $xR + 50 }}"
                            y2="{{ $yB }}" stroke="{{ $ink }}" stroke-width="2.2" />

                        {{-- MARCO EXTERIOR (anillo de aluminio) --}}
                        <g filter="url(#softShadow)">
                            <path fill-rule="evenodd" fill="url(#alu)" stroke="{{ $ink }}"
                                stroke-width="1.8"
                                d="M{{ $x0 }} {{ $y0 }}h{{ $W }}v{{ $H }}h-{{ $W }}Z
               M{{ $p['innerX'] }} {{ $p['innerY'] }}h{{ $p['innerW'] }}v{{ $p['innerH'] }}h-{{ $p['innerW'] }}Z" />
                        </g>
                        <rect x="{{ $x0 + $fm * 0.3 }}" y="{{ $y0 + $fm * 0.3 }}"
                            width="{{ max(1, $W - $fm * 0.6) }}" height="{{ max(1, $H - $fm * 0.6) }}"
                            fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="1" />

                        {{-- SOBRELUZ --}}
                        @if ($p['haySobreluz'])
                            @php
                                $slx = $p['innerX'] + $fh;
                                $sly = $p['innerY'] + $fh;
                                $slw = max(2, $p['innerW'] - $fh * 2);
                                $slh = max(2, $p['sobreluzH'] - $fh * 2);
                            @endphp
                            <path fill-rule="evenodd" fill="url(#aluLeaf)" stroke="{{ $ink }}"
                                stroke-width="1.3"
                                d="M{{ $p['innerX'] }} {{ $p['innerY'] }}h{{ $p['innerW'] }}v{{ $p['sobreluzH'] }}h-{{ $p['innerW'] }}Z
               M{{ $slx }} {{ $sly }}h{{ $slw }}v{{ $slh }}h-{{ $slw }}Z" />
                            <rect x="{{ $slx }}" y="{{ $sly }}" width="{{ $slw }}"
                                height="{{ $slh }}" fill="url(#glass)" stroke="#2C5F8A"
                                stroke-opacity=".55" stroke-width="1" />
                            <path
                                d="M{{ $slx + $slw * 0.12 }} {{ $sly + $slh }} L{{ $slx + $slw * 0.2 }} {{ $sly }} L{{ $slx + $slw * 0.27 }} {{ $sly }} L{{ $slx + $slw * 0.19 }} {{ $sly + $slh }}Z"
                                fill="#fff" fill-opacity=".45" />
                            <path
                                d="M{{ $slx + $slw * 0.3 }} {{ $sly + $slh }} L{{ $slx + $slw * 0.33 }} {{ $sly }} L{{ $slx + $slw * 0.35 }} {{ $sly }} L{{ $slx + $slw * 0.32 }} {{ $sly + $slh }}Z"
                                fill="#fff" fill-opacity=".35" />

                            {{-- travesaño --}}
                            <rect x="{{ $p['innerX'] }}" y="{{ $p['innerY'] + $p['sobreluzH'] }}"
                                width="{{ $p['innerW'] }}" height="{{ $p['travesanoPx'] }}" fill="url(#alu)"
                                stroke="{{ $ink }}" stroke-width="1.4" />
                        @endif

                        {{-- HOJAS: primero fijas, después corredizas (quedan por encima, como en obra) --}}
                        @foreach ([false, true] as $pasada)
                            @foreach ($p['panels'] as $i => $panel)
                                @continue($panel['esCorrediza'] !== $pasada)

                                @php
                                    $leafH = max($p['filaH'], 3);
                                    $gcx = $panel['gx'] + $panel['gw'] / 2;
                                    $gcy = $panel['gy'] + $panel['gh'] / 2;
                                @endphp

                                <g @if ($panel['esCorrediza']) filter="url(#softShadow)" @endif>
                                    <path fill-rule="evenodd" fill="url(#aluLeaf)" stroke="{{ $ink }}"
                                        stroke-width="{{ $panel['esCorrediza'] ? 1.6 : 1.3 }}"
                                        d="M{{ $panel['hx'] }} {{ $p['filaY'] }}h{{ $panel['hw'] }}v{{ $leafH }}h-{{ $panel['hw'] }}Z
                       M{{ $panel['gx'] }} {{ $panel['gy'] }}h{{ $panel['gw'] }}v{{ $panel['gh'] }}h-{{ $panel['gw'] }}Z" />
                                </g>

                                {{-- vidrio + reflejos --}}
                                <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}" width="{{ $panel['gw'] }}"
                                    height="{{ $panel['gh'] }}" fill="url(#glass)" stroke="#2C5F8A"
                                    stroke-opacity=".55" stroke-width="1" />
                                <path
                                    d="M{{ $panel['gx'] + $panel['gw'] * 0.12 }} {{ $panel['gy'] + $panel['gh'] }} L{{ $panel['gx'] + $panel['gw'] * 0.45 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.55 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.22 }} {{ $panel['gy'] + $panel['gh'] }}Z"
                                    fill="#fff" fill-opacity=".40" />
                                <path
                                    d="M{{ $panel['gx'] + $panel['gw'] * 0.6 }} {{ $panel['gy'] + $panel['gh'] }} L{{ $panel['gx'] + $panel['gw'] * 0.72 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.76 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.64 }} {{ $panel['gy'] + $panel['gh'] }}Z"
                                    fill="#fff" fill-opacity=".28" />

                                @if ($panel['esCorrediza'])
                                    {{-- flecha de desplazamiento (alterna sentido) --}}
                                    @php
                                        $s = min(34, $panel['gw'] * 0.28);
                                        $dir = $i % 2 === 0 ? 1 : -1;
                                    @endphp
                                    <line x1="{{ $gcx - $s * $dir }}" y1="{{ $gcy }}"
                                        x2="{{ $gcx + $s * $dir }}" y2="{{ $gcy }}" stroke="#334155"
                                        stroke-opacity=".7" stroke-width="2.2" stroke-linecap="round"
                                        marker-end="url(#slideArrow)" />

                                    {{-- tirador --}}
                                    <rect x="{{ $panel['handleX'] }}" y="{{ $panel['handleY'] }}" width="6"
                                        height="30" rx="3" fill="#BE123C" stroke="#fff"
                                        stroke-width="1" />

                                    {{-- mosquitero --}}
                                    @if ($mosquitero)
                                        <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}"
                                            width="{{ $panel['gw'] }}" height="{{ $panel['gh'] }}"
                                            fill="url(#mesh)" />
                                        <rect x="{{ $panel['hx'] + 4 }}" y="{{ $p['filaY'] + 4 }}"
                                            width="{{ max($panel['hw'] - 8, 2) }}"
                                            height="{{ max($p['filaH'] - 8, 2) }}" fill="none" stroke="#475569"
                                            stroke-width="1" stroke-dasharray="4 3" />
                                    @endif
                                @endif
                            @endforeach
                        @endforeach

                        {{-- RIELES --}}
                        @if ($p['nCorredizas'] > 0)
                            <line x1="{{ $x0 }}" y1="{{ $p['railY1'] }}" x2="{{ $xR }}"
                                y2="{{ $p['railY1'] }}" stroke="#475569" stroke-width="1.2"
                                stroke-dasharray="6 4" />
                            <line x1="{{ $x0 }}" y1="{{ $p['railY2'] }}" x2="{{ $xR }}"
                                y2="{{ $p['railY2'] }}" stroke="#475569" stroke-width="1.2"
                                stroke-dasharray="6 4" />
                        @endif

                        {{-- COTAS POR HOJA --}}
                        @if ($mostrarCotaHoja)
                            @foreach ($p['panels'] as $panel)
                                {!! $dimH(
                                    $panel['hx'],
                                    $panel['hx'] + $panel['hw'],
                                    $yB + 28,
                                    $p['filaY'] + $p['filaH'],
                                    $panel['cotaLabel'],
                                    10,
                                ) !!}
                            @endforeach
                        @endif

                        {{-- COTAS GENERALES --}}
                        @if ($mostrarCotas)
                            {!! $dimH(
                                $x0,
                                $xR,
                                $y0 - 52,
                                $y0 - 20,
                                number_format($p['Wcm'], 1) . ' cm  ·  ' . number_format($p['Wcm'] * 10, 0) . ' mm',
                                13,
                            ) !!}
                            {!! $dimH($x0, $xR, $y0 - 28, $y0 - 20, 'FAB ' . number_format($p['anchoFabricacion'], 1) . ' cm', 10) !!}

                            {!! $dimV(
                                $x0 - 44,
                                $y0,
                                $yB,
                                $x0 - 20,
                                number_format($p['Hcm'], 1) . ' cm  ·  ' . number_format($p['Hcm'] * 10, 0) . ' mm',
                                13,
                            ) !!}

                            @if ($p['haySobreluz'])
                                {!! $dimV(
                                    $xR + 44,
                                    $p['innerY'],
                                    $p['innerY'] + $p['sobreluzH'],
                                    $xR + 20,
                                    'SL ' . number_format($p['Puente'], 1),
                                    11,
                                ) !!}
                                {!! $dimV(
                                    $xR + 44,
                                    $p['filaY'],
                                    $p['filaY'] + $p['filaH'],
                                    $xR + 20,
                                    'HOJAS ' . number_format($bandaHojas, 1),
                                    11,
                                ) !!}
                            @endif
                        @endif

                        {{-- ETIQUETAS DE PARTES --}}
                        @if ($mostrarEtiquetas)
                            {{-- fila superior --}}
                            {!! $call($x0 + $W * 0.04, $y0 + $fm / 2, 'Marco superior', $x0 + 10, $y0 - 80, 'te') !!}

                            @if ($p['haySobreluz'])
                                {!! $call(
                                    $p['innerX'] + $p['innerW'] / 2,
                                    $p['innerY'] + $p['sobreluzH'] / 2,
                                    'Sobreluz fijo',
                                    $x0 + 30,
                                    $y0 - 80,
                                    't',
                                ) !!}
                            @endif

                            @if ($pCorr)
                                {!! $call(
                                    $pCorr['gx'] + $pCorr['gw'] / 2,
                                    $p['filaY'] + $p['filaH'] * 0.22,
                                    'Hoja corrediza',
                                    $x0 + 160,
                                    $y0 - 80,
                                    't',
                                ) !!}
                            @endif

                            {{-- lateral izquierdo --}}
                            @if ($pFijo)
                                {!! $call(
                                    $pFijo['gx'] + $pFijo['gw'] / 2,
                                    $p['filaY'] + $p['filaH'] * 0.35,
                                    'Paño fijo',
                                    $x0 - 62,
                                    $y0 + $H * 0.27,
                                    'l',
                                ) !!}
                            @endif

                            {!! $call($x0 + $fm / 2, $y0 + $H * 0.5, 'Jamba lateral', $x0 - 62, $y0 + $H * 0.5, 'l') !!}

                            @if ($p['nCorredizas'] > 0)
                                {!! $call($x0 + $W * 0.1, $p['railY1'], 'Riel corredizo', $x0 - 62, $y0 + $H * 0.73, 'l') !!}
                            @endif
                        @endif

                        {{-- CARTUCHO --}}
                        @php
                            $tbX = 70;
                            $tbY = 616;
                            $tbW = 960;
                            $tbH = 124;
                            $cols = [
                                $tbX + 26,
                                $tbX + 26 + $tbW * 0.25,
                                $tbX + 26 + $tbW * 0.5,
                                $tbX + 26 + $tbW * 0.75,
                            ];
                            $celdas = [
                                [0, 18, 'SISTEMA', 'Corredizo · Mampara'],
                                [
                                    0,
                                    56,
                                    'DIMENSIÓN',
                                    number_format($p['Wcm'], 1) . ' x ' . number_format($p['Hcm'], 1) . ' cm',
                                ],
                                [
                                    0,
                                    94,
                                    'FABRICACIÓN',
                                    number_format($p['anchoFabricacion'], 1) .
                                    ' x ' .
                                    number_format($p['altoFabricacion'], 1) .
                                    ' cm',
                                ],
                                [1, 18, 'N° HOJAS', $p['numHojas'] . ' hojas'],
                                [1, 56, 'VIDRIO', $p['vidrioLabel']],
                                [1, 94, 'MOSQUITERO', $mosquitero ? 'Sí' : 'No'],
                                [2, 18, 'PERFIL MARCO', '45 mm'],
                                [2, 56, 'PERFIL HOJA', '40 mm'],
                                [2, 94, 'ESCALA', '1:' . $p['escala']],
                                [
                                    3,
                                    18,
                                    'PLANO',
                                    'SC-' . number_format($p['Wcm'], 0) . 'x' . number_format($p['Hcm'], 0),
                                ],
                                [3, 56, 'FECHA', $p['fecha']],
                                [3, 94, 'REVISIÓN', 'A'],
                            ];
                        @endphp

                        <rect x="{{ $tbX }}" y="{{ $tbY }}" width="{{ $tbW }}"
                            height="{{ $tbH }}" rx="10" fill="#fff" stroke="#CBD5E1"
                            stroke-width="1" />
                        <rect x="{{ $tbX }}" y="{{ $tbY }}" width="8"
                            height="{{ $tbH }}" rx="4" fill="{{ $acc }}" />

                        @foreach ([1, 2, 3] as $k)
                            <line x1="{{ $cols[$k] - 16 }}" y1="{{ $tbY + 14 }}" x2="{{ $cols[$k] - 16 }}"
                                y2="{{ $tbY + $tbH - 14 }}" stroke="#E2E8F0" stroke-width="1" />
                        @endforeach

                        @foreach ($celdas as [$c, $dy, $label, $value])
                            <text x="{{ $cols[$c] }}" y="{{ $tbY + $dy + 10 }}"
                                font-family="JetBrains Mono, monospace" font-size="9.5" fill="#94A3B8"
                                letter-spacing="1">{{ $label }}</text>
                            <text x="{{ $cols[$c] }}" y="{{ $tbY + $dy + 27 }}" font-family="Inter, sans-serif"
                                font-size="13" font-weight="700"
                                fill="{{ $ink }}">{{ $value }}</text>
                        @endforeach
                    </svg>

                </div>
            </div>

            <div x-show="!mostrarPlano" x-cloak class="py-16 text-center text-sm italic text-slate-400">
                Plano oculto
            </div>
        </section>
    </div>

    {{-- ===================== CORTES Y PERFILES ===================== --}}
    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">

        {{-- CORTE DE VIDRIOS --}}
        <div class="{{ $tarjeta }} overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-sky-600 to-indigo-600 px-5 py-4 text-white">
                <div class="flex items-center gap-2 font-bold">
                    <i class="fa-solid fa-gem"></i> Corte de vidrios
                </div>
                <div class="flex items-center gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-white/20 px-3 py-1">{{ count($this->cortesVidrio) }} piezas</span>
                    <span class="rounded-full bg-white/20 px-3 py-1">{{ $pl['vidrioLabel'] }}</span>
                </div>
            </div>

            <div
                class="grid grid-cols-[1fr_auto_auto_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Hoja</span>
                <span>Tipo</span>
                <span class="w-20 text-right">Ancho</span>
                <span class="w-20 text-right">Alto</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($this->cortesVidrio as $corte)
                    <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-4 px-5 py-3 transition hover:bg-indigo-50/50"
                        wire:key="corte-{{ $loop->index }}">
                        <span class="text-sm font-bold text-slate-800">{{ $corte['hoja'] }}</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide
                            {{ $corte['tipo'] === 'Fijo' ? 'bg-indigo-100 text-indigo-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $corte['tipo'] }}
                        </span>
                        <span
                            class="w-20 text-right font-mono text-xs font-bold text-slate-700">{{ number_format($corte['ancho'], 1) }}
                            cm</span>
                        <span
                            class="w-20 text-right font-mono text-xs font-bold text-slate-700">{{ number_format($corte['alto'], 1) }}
                            cm</span>
                    </div>
                @empty
                    <div class="py-12 text-center text-sm italic text-slate-400">Agrega hojas para ver los cortes</div>
                @endforelse
            </div>

            <details class="border-t border-slate-200 px-5 py-3 text-[11px] leading-relaxed text-slate-500">
                <summary class="cursor-pointer select-none font-bold uppercase tracking-wider text-slate-500">
                    Descuentos aplicados</summary>
                <ul class="mt-2 space-y-1">
                    <li><b class="text-slate-700">Primero:</b> ancho − 0.5 cm y alto − 0.5 cm.</li>
                    <li><b class="text-indigo-600">Fijo:</b> ancho hoja − 4 cm · alto de banda − 5.5 cm.</li>
                    <li><b class="text-rose-600">Corredizo:</b> ancho hoja + 4 cm · alto de banda − 6.5 cm.</li>
                    <li><b class="text-slate-700">Sobreluz:</b> ancho fabricación − 4 cm · altura de sobreluz − 5.5 cm.
                    </li>
                </ul>
            </details>
        </div>

        {{-- PERFILES --}}
        <div class="{{ $tarjeta }} overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-4 text-white">
                <div class="flex items-center gap-2 font-bold">
                    <i class="fa-solid fa-list-check"></i> Perfiles y accesorios
                </div>
                <span
                    class="rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold">{{ count($this->perfilesMampara) }}
                    ítems</span>
            </div>

            <div
                class="grid grid-cols-[1fr_auto_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Detalle</span>
                <span>Medida</span>
                <span class="w-9 text-center">Cant.</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($this->perfilesMampara as $perfil)
                    <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4 px-5 py-3 transition hover:bg-indigo-50/50"
                        wire:key="perfil-{{ $loop->index }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="rounded-md bg-indigo-50 px-2 py-1 font-mono text-[11px] font-bold text-indigo-600">{{ $perfil['codigo'] }}</span>
                            <span class="truncate text-sm font-bold text-slate-800">{{ $perfil['nombre'] }}</span>
                        </div>
                        <span
                            class="whitespace-nowrap rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-xs font-bold text-slate-700">
                            {{ number_format($perfil['medida'], 1) }} cm
                        </span>
                        <span
                            class="flex h-8 w-9 items-center justify-center rounded-lg bg-emerald-100 font-mono text-xs font-extrabold text-emerald-700">
                            {{ $perfil['cant'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="border-t border-slate-200 px-5 py-3 text-[11px] leading-relaxed text-slate-500">
                7955, 5283 y 8413 usan la medida de fabricación:
                <b class="text-slate-700">{{ number_format($this->anchoFabricacion, 1) }} cm</b>
                (el descuento inicial de 0.5 cm ya está aplicado).
            </p>
        </div>
    </div>

    <p class="mt-5 text-center text-[11px] text-slate-400">
        Plano referencial de fabricación · perfiles y cotas aproximados al sistema de mamparas correderas
        de aluminio "Corredizo" · verificar con proveedor antes de ejecutar la obra.
    </p>

</div>
<?php

use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    /*
    |--------------------------------------------------------------------------
    | MEDIDAS
    |--------------------------------------------------------------------------
    */

    public float $ancho = 300;
    public float $alto = 220;
    public bool $conSobreluz = true;
    public float $puente = 130;

    public int $corredizas = 1;
    public int $fijos = 2;

    /*
    |--------------------------------------------------------------------------
    | OPCIONES DEL PLANO (antes eran inputs "sueltos" leídos por JS)
    |--------------------------------------------------------------------------
    */

    public string $vidrio = 'doble';
    public bool $mosquitero = false;

    public bool $mostrarCotas = true;
    public bool $mostrarCotaHoja = false;
    public bool $mostrarEtiquetas = true;

    /*
    |--------------------------------------------------------------------------
    | DATOS
    |--------------------------------------------------------------------------
    */

    public array $datos = [];
    public array $data = [];

    /*
    |--------------------------------------------------------------------------
    | MAMPARAS
    |--------------------------------------------------------------------------
    */

    public array $mamparas = [];
    public int $mamparaActiva = 0;

    /*
    |--------------------------------------------------------------------------
    | DESCUENTO INICIAL
    |--------------------------------------------------------------------------
    |
    | Este descuento se aplica ANTES de cualquier otro descuento.
    |
    | Ancho ingresado - 0.5
    | Alto ingresado  - 0.5
    |
    */

    private const DESCUENTO_INICIAL = 0.5;

    /*
    |--------------------------------------------------------------------------
    | CONSTANTES DEL PLANO (antes vivían dentro del <script>)
    |--------------------------------------------------------------------------
    */

    private const VIEW_W = 1100;
    private const VIEW_H = 760;

    private const MARGIN_LEFT = 160;
    private const MARGIN_RIGHT = 150;
    private const MARGIN_TOP = 90;
    private const MARGIN_BOTTOM = 190;

    private const PERFIL_MARCO_MM = 45;
    private const PERFIL_HOJA_MM = 40;
    private const OVERLAP_MM = 20;

    private const VIDRIO_LABELS = [
        'simple' => 'Vidrio simple 6mm',
        'doble' => 'Vidrio templado 6mm',
        'dvh' => 'DVH 6+12+6mm',
    ];

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->procesarPerfiles();

        $guardadas = session('mamparas');

        if (is_array($guardadas) && count($guardadas) > 0) {
            $this->mamparas = $guardadas;
            $this->mamparaActiva = 0;

            $this->cargarMamparaActiva();
        } else {
            $this->mamparas = [array_merge(['nombre' => 'M - 1'], $this->snapshotMampara())];

            $this->mamparaActiva = 0;

            session()->put('mamparas', $this->mamparas);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MEDIDA DE FABRICACIÓN
    |--------------------------------------------------------------------------
    */

    public function getAnchoFabricacionProperty(): float
    {
        return max(0, (float) $this->ancho - self::DESCUENTO_INICIAL);
    }

    public function getAltoFabricacionProperty(): float
    {
        return max(0, (float) $this->alto - self::DESCUENTO_INICIAL);
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL HOJAS
    |--------------------------------------------------------------------------
    */

    public function getTotalHojasProperty(): int
    {
        return max(0, (int) $this->fijos) + max(0, (int) $this->corredizas);
    }

    /*
    |--------------------------------------------------------------------------
    | ALTURA DEL SOBRELUZ
    |--------------------------------------------------------------------------
    |
    | "puente" representa la altura de la banda PRINCIPAL (donde están
    | las hojas), medida desde abajo. El sobreluz se lleva lo que sobra:
    |
    | Ej: alto = 250, puente = 200  →  sobreluz = 250 - 200 = 50 cm
    |
    */

    public function getSobreluzAlturaProperty(): float
    {
        if (!$this->conSobreluz || $this->puente <= 0) {
            return 0;
        }

        return max(0, $this->altoFabricacion - $this->puente);
    }

    /*
    |--------------------------------------------------------------------------
    | ALTO BANDA FIJA / CORREDIZA
    |--------------------------------------------------------------------------
    */

    public function getAltoBandaFijaProperty(): float
    {
        if ($this->fijos <= 0) {
            return 0;
        }

        if ($this->conSobreluz && $this->puente > 0) {
            return min($this->altoFabricacion, max(0, $this->puente));
        }

        return $this->altoFabricacion;
    }

    public function getAltoBandaCorredizaProperty(): float
    {
        if ($this->corredizas <= 0) {
            return 0;
        }

        if ($this->conSobreluz && $this->puente > 0) {
            return min($this->altoFabricacion, max(0, $this->puente));
        }

        return $this->altoFabricacion;
    }

    /*
    |--------------------------------------------------------------------------
    | CORTES DE VIDRIO
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    |
    | 1. Primero se aplica -0.5 al ancho y alto total.
    | 2. Después se aplican los descuentos propios de mampara.
    |
    */

    public function getCortesVidrioProperty(): array
    {
        $cortes = [];

        $W = $this->anchoFabricacion;

        // El ancho base se reparte entre el TOTAL de hojas (fijos + corredizas),
        // no entre las hojas de cada tipo por separado.
        $totalHojas = max(1, $this->totalHojas);
        $anchoBase = $W / $totalHojas;

        if ($this->fijos > 0) {
            $anchoFijo = max(0, $anchoBase - 4);
            $altoFijo = max(0, $this->altoBandaFija - 5.5);

            for ($i = 1; $i <= $this->fijos; $i++) {
                $cortes[] = [
                    'hoja' => 'Fijo ' . $i,
                    'tipo' => 'Fijo',
                    'ancho' => $anchoFijo,
                    'alto' => $altoFijo,
                ];
            }
        }

        if ($this->corredizas > 0) {
            $anchoCorrediza = max(0, $anchoBase + 4);
            $altoCorrediza = max(0, $this->altoBandaCorrediza - 6.5);

            for ($i = 1; $i <= $this->corredizas; $i++) {
                $cortes[] = [
                    'hoja' => 'Corredizo ' . $i,
                    'tipo' => 'Corredizo',
                    'ancho' => $anchoCorrediza,
                    'alto' => $altoCorrediza,
                ];
            }
        }

        $sobreluzAltura = $this->sobreluzAltura;

        if ($sobreluzAltura > 0) {
            $anchoSobreluz = max(0, $W - 4);
            $altoSobreluz = max(0, $sobreluzAltura - 5.5);

            $cortes[] = [
                'hoja' => 'Sobreluz',
                'tipo' => 'Fijo',
                'ancho' => $anchoSobreluz,
                'alto' => $altoSobreluz,
            ];
        }

        return $cortes;
    }

    /*
    |--------------------------------------------------------------------------
    | DISTRIBUCIÓN DE HOJAS
    |--------------------------------------------------------------------------
    */

    public function getDistribucionHojasProperty(): array
    {
        $fijosIzquierda = (int) ceil($this->fijos / 2);
        $fijosDerecha = $this->fijos - $fijosIzquierda;

        $tipos = [];

        for ($i = 0; $i < $fijosIzquierda; $i++) {
            $tipos[] = 'F';
        }

        for ($i = 0; $i < $this->corredizas; $i++) {
            $tipos[] = 'C';
        }

        for ($i = 0; $i < $fijosDerecha; $i++) {
            $tipos[] = 'F';
        }

        return $tipos ?: ['F'];
    }

    /*
    |--------------------------------------------------------------------------
    | PERFILES
    |--------------------------------------------------------------------------
    */

    public function getPerfilesMamparaProperty(): array
    {
        $W = $this->anchoFabricacion;
        $P = $this->sobreluzAltura;

        return [['codigo' => '7955', 'nombre' => 'U de 3/4', 'medida' => $W, 'cant' => 1], ['codigo' => '5283', 'nombre' => 'Tubo multifuncional', 'medida' => $W, 'cant' => 1], ['codigo' => '8413', 'nombre' => 'Riel baja', 'medida' => $W, 'cant' => 1], ['codigo' => '3003', 'nombre' => 'U de 1/2', 'medida' => $P / 2 + 4.2, 'cant' => max(0, $this->fijos)], ['codigo' => '4104', 'nombre' => 'Ángulo de 1/2 económico', 'medida' => max(0, $P - 0.3), 'cant' => max(0, $this->fijos)], ['codigo' => '8220', 'nombre' => 'H 6 mm', 'medida' => $P / 2 + 3.6, 'cant' => max(0, $this->corredizas)]];
    }

    /*
    |--------------------------------------------------------------------------
    | GEOMETRÍA DEL PLANO (reemplaza toda la lógica que antes vivía en JS)
    |--------------------------------------------------------------------------
    */

    public function getPlanoProperty(): array
    {
        $Wcm = max(0, (float) $this->ancho);
        $Hcm = max(0, (float) $this->alto);
        $SobreluzAltura = $this->sobreluzAltura;

        $nFijos = max(0, (int) $this->fijos);
        $nCorredizas = max(0, (int) $this->corredizas);
        $numHojas = max(1, $nFijos + $nCorredizas);

        $anchoFabricacion = $this->anchoFabricacion;
        $altoFabricacion = $this->altoFabricacion;

        $drawW = self::VIEW_W - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $drawH = self::VIEW_H - self::MARGIN_TOP - self::MARGIN_BOTTOM;

        $Wmm = max(1, $anchoFabricacion * 10);
        $Hmm = max(1, $altoFabricacion * 10);

        $scale = min($drawW / $Wmm, $drawH / $Hmm);
        $escala = max(1, round((1 / $scale) * 10) / 10);

        $w = $Wmm * $scale;
        $h = $Hmm * $scale;

        $x0 = self::MARGIN_LEFT + ($drawW - $w) / 2;
        $y0 = self::MARGIN_TOP + ($drawH - $h) / 2;

        $fm = self::PERFIL_MARCO_MM * $scale;
        $fh = self::PERFIL_HOJA_MM * $scale;
        $ov = self::OVERLAP_MM * $scale;

        $innerX = $x0 + $fm;
        $innerY = $y0 + $fm;
        $innerW = max(2, $w - $fm * 2);
        $innerH = max(2, $h - $fm * 2);

        $haySobreluz = $this->conSobreluz && $SobreluzAltura > 0;
        $puentePx = min($innerH * 0.75, $SobreluzAltura * 10 * $scale);
        $travesanoPx = $haySobreluz ? max(5, $fm * 0.6) : 0;
        $sobreluzH = $haySobreluz ? max(5, $puentePx) : 0;

        $filaY = $innerY + $sobreluzH + $travesanoPx;
        $filaH = max(10, $innerH - $sobreluzH - $travesanoPx);

        $filaTipos = $this->distribucionHojas;
        $n = count($filaTipos);
        $wRaw = $innerW / max(1, $n);

        // Ancho real de cada hoja: el ancho de fabricación se reparte entre
        // el TOTAL de hojas (fijos + corredizas), y luego se le suma 4 cm si
        // es corrediza o se le resta 4 cm si es fija. Misma base que usa
        // getCortesVidrioProperty() para la tabla de corte de vidrios.
        $anchoBaseCota = $anchoFabricacion / max(1, $n);
        $anchoFijoCota = max(0, $anchoBaseCota - 4);
        $anchoCorredizaCota = max(0, $anchoBaseCota + 4);

        $panels = [];

        foreach ($filaTipos as $i => $tipo) {
            $esCorrediza = $tipo === 'C';

            $leftOverlap = $esCorrediza && $i > 0 ? $ov / 2 : 0;
            $rightOverlap = $esCorrediza && $i < $n - 1 ? $ov / 2 : 0;

            $hx = $innerX + $i * $wRaw - $leftOverlap;
            $hw = $wRaw + $leftOverlap + $rightOverlap;

            $gx = $hx + $fh;
            $gy = $filaY + $fh;
            $gw = max(2, $hw - $fh * 2);
            $gh = max(2, $filaH - $fh * 2);

            $panels[] = [
                'esCorrediza' => $esCorrediza,
                'hx' => $hx,
                'hw' => max($hw, 3),
                'gx' => $gx,
                'gy' => $gy,
                'gw' => $gw,
                'gh' => $gh,
                'handleX' => $hx + $hw - 12,
                'handleY' => $filaY + $filaH / 2 - 15,
                'cotaLabel' => number_format($esCorrediza ? $anchoCorredizaCota : $anchoFijoCota, 1) . ' cm',
            ];
        }

        $railY1 = null;
        $railY2 = null;

        if ($nCorredizas > 0) {
            $railY1 = $filaY + $filaH - max(4, $fm * 0.35);
            $railY2 = $filaY + $filaH - max(7, $fm * 0.65);
        }

        $areaCm2 = 0;

        foreach ($this->cortesVidrio as $c) {
            $areaCm2 += max(0, $c['ancho']) * max(0, $c['alto']);
        }

        $areaM2 = $areaCm2 / 10000;
        $perimM = (($Wcm + $Hcm) * 2) / 100;

        return [
            'Wcm' => $Wcm,
            'Hcm' => $Hcm,
            'Puente' => $SobreluzAltura,
            'ConSobreluz' => $haySobreluz,
            'nFijos' => $nFijos,
            'nCorredizas' => $nCorredizas,
            'numHojas' => $numHojas,
            'anchoFabricacion' => $anchoFabricacion,
            'altoFabricacion' => $altoFabricacion,
            'escala' => $escala,
            'w' => $w,
            'h' => $h,
            'x0' => $x0,
            'y0' => $y0,
            'fm' => $fm,
            'fh' => $fh,
            'innerX' => $innerX,
            'innerY' => $innerY,
            'innerW' => $innerW,
            'innerH' => $innerH,
            'haySobreluz' => $haySobreluz,
            'sobreluzH' => $sobreluzH,
            'travesanoPx' => $travesanoPx,
            'filaY' => $filaY,
            'filaH' => $filaH,
            'panels' => $panels,
            'railY1' => $railY1,
            'railY2' => $railY2,
            'areaM2' => $areaM2,
            'perimM' => $perimM,
            'vidrioLabel' => self::VIDRIO_LABELS[$this->vidrio] ?? 'Vidrio 6mm',
            'fecha' => now()->translatedFormat('d/m/Y'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SNAPSHOT / GUARDADO / CARGA DE MAMPARAS
    |--------------------------------------------------------------------------
    */

    private function snapshotMampara(): array
    {
        return [
            'ancho' => (float) $this->ancho,
            'alto' => (float) $this->alto,
            'conSobreluz' => $this->conSobreluz,
            'puente' => (float) $this->puente,
            'corredizas' => max(0, (int) $this->corredizas),
            'fijos' => max(0, (int) $this->fijos),
            'vidrio' => $this->vidrio,
            'mosquitero' => $this->mosquitero,
            'mostrarCotas' => $this->mostrarCotas,
            'mostrarCotaHoja' => $this->mostrarCotaHoja,
            'mostrarEtiquetas' => $this->mostrarEtiquetas,
            'datos' => $this->datos,
        ];
    }

    private function guardarMamparaActual(): void
    {
        if (!isset($this->mamparas[$this->mamparaActiva])) {
            return;
        }

        $nombre = $this->mamparas[$this->mamparaActiva]['nombre'] ?? 'M - ' . ($this->mamparaActiva + 1);

        $this->mamparas[$this->mamparaActiva] = array_merge(['nombre' => $nombre], $this->snapshotMampara());

        session()->put('mamparas', $this->mamparas);
    }

    private function cargarMampara(int $i): void
    {
        if (!isset($this->mamparas[$i])) {
            return;
        }

        $p = $this->mamparas[$i];

        $this->ancho = (float) ($p['ancho'] ?? 300);
        $this->alto = (float) ($p['alto'] ?? 220);
        $this->conSobreluz = (bool) ($p['conSobreluz'] ?? true);
        $this->puente = (float) ($p['puente'] ?? 130);

        $this->corredizas = max(0, (int) ($p['corredizas'] ?? 1));
        $this->fijos = max(0, (int) ($p['fijos'] ?? 2));

        $this->vidrio = $p['vidrio'] ?? 'doble';
        $this->mosquitero = (bool) ($p['mosquitero'] ?? false);

        $this->mostrarCotas = (bool) ($p['mostrarCotas'] ?? true);
        $this->mostrarCotaHoja = (bool) ($p['mostrarCotaHoja'] ?? false);
        $this->mostrarEtiquetas = (bool) ($p['mostrarEtiquetas'] ?? true);

        $this->datos = $p['datos'] ?? [];
    }

    public function cambiarMampara(int $i): void
    {
        if (!isset($this->mamparas[$i])) {
            return;
        }

        $this->guardarMamparaActual();

        $this->mamparaActiva = $i;

        $this->cargarMamparaActiva();
    }

    private function cargarMamparaActiva(): void
    {
        if (!isset($this->mamparas[$this->mamparaActiva])) {
            return;
        }

        $this->cargarMampara($this->mamparaActiva);
    }

    public function agregarMampara(): void
    {
        $this->guardarMamparaActual();

        $this->ancho = 300;
        $this->alto = 220;
        $this->conSobreluz = true;
        $this->puente = 130;

        $this->corredizas = 1;
        $this->fijos = 2;

        $this->vidrio = 'doble';
        $this->mosquitero = false;
        $this->mostrarCotas = true;
        $this->mostrarCotaHoja = false;
        $this->mostrarEtiquetas = true;

        $this->datos = [];

        $this->mamparas[] = array_merge(['nombre' => 'M - ' . (count($this->mamparas) + 1)], $this->snapshotMampara());

        $this->mamparaActiva = count($this->mamparas) - 1;

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('correcto', 'Vista agregada con exito');
    }

    public function eliminarMampara(int $index): void
    {
        if (count($this->mamparas) <= 1) {
            return;
        }

        if (!isset($this->mamparas[$index])) {
            return;
        }

        unset($this->mamparas[$index]);

        $this->mamparas = array_values($this->mamparas);

        if ($this->mamparaActiva >= count($this->mamparas)) {
            $this->mamparaActiva = count($this->mamparas) - 1;
        }

        $this->cargarMamparaActiva();

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('delete', 'Vista eliminada con exito');
    }

    private function resetMamparas(): void
    {
        $this->mamparas = [
            array_merge(
                ['nombre' => 'M - 1'],
                [
                    'ancho' => 300,
                    'alto' => 220,
                    'conSobreluz' => true,
                    'puente' => 130,
                    'corredizas' => 1,
                    'fijos' => 2,
                    'vidrio' => 'doble',
                    'mosquitero' => false,
                    'mostrarCotas' => true,
                    'mostrarCotaHoja' => false,
                    'mostrarEtiquetas' => true,
                    'datos' => [],
                ],
            ),
        ];

        $this->mamparaActiva = 0;

        $this->cargarMamparaActiva();

        session()->put('mamparas', $this->mamparas);
    }

    /*
    |--------------------------------------------------------------------------
    | EXCEL
    |--------------------------------------------------------------------------
    */

    public function procesarPerfiles(): void
    {
        $ruta = public_path('datos.xlsx');

        if (!file_exists($ruta)) {
            $this->data = [];
            return;
        }

        try {
            $hojas = Excel::toArray([], $ruta);

            $this->data = collect($hojas[0] ?? [])
                ->pluck(0)
                ->filter()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $this->data = [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IMPRIMIR / LIMPIAR
    |--------------------------------------------------------------------------
    */

    public function imprimirTodo(): void
    {
        $this->guardarMamparaActual();

        session()->put('mamparas', $this->mamparas);

        $this->dispatch('imprimir-mamparas');
    }

    protected $listeners = ['limpiar-mamparas' => 'limpiarMamparas'];

    public function limpiarMamparas(): void
    {
        session()->forget('mamparas');

        $this->resetMamparas();

        $this->dispatch('correcto', 'Mamparas limpiadas correctamente');
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDADO AUTOMÁTICO
    |--------------------------------------------------------------------------
    */

    public function updated(string $property): void
    {
        if (in_array($property, ['ancho', 'alto', 'conSobreluz', 'puente', 'corredizas', 'fijos', 'datos', 'vidrio', 'mosquitero', 'mostrarCotas', 'mostrarCotaHoja', 'mostrarEtiquetas'], true)) {
            $this->guardarMamparaActual();
        }
    }
};
?>

<div>
    @php
    $tarjeta = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    $inputClase =
        'w-full rounded-xl border border-slate-300 bg-white pl-3 pr-10 py-2.5 text-sm font-semibold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none';
    $opciones = [
        ['mosquitero', 'Mosquitero', 'fa-border-none'],
        ['mostrarCotas', 'Cotas generales', 'fa-ruler-combined'],
        ['mostrarCotaHoja', 'Cota por hoja', 'fa-arrows-left-right'],
        ['mostrarEtiquetas', 'Etiquetas de partes', 'fa-tags'],
    ];
    $pl = $this->plano;
@endphp

<div class="mx-auto max-w-7xl p-3 font-sans text-slate-800 md:p-6">

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    {{-- LOADING --}}
    <div wire:loading class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm">
        <div class="absolute inset-0 flex items-center justify-center">
            <img src="{{ asset('img/tape.gif') }}" alt=""
                class="h-32 w-32 rounded-full shadow-2xl ring-4 ring-white">
        </div>
    </div>

    {{-- ENCABEZADO --}}
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-indigo-500">Calculadora de mamparas</p>
            <h1 class="text-3xl font-black tracking-tight text-slate-800">
                {{ $mamparas[$mamparaActiva]['nombre'] ?? '—' }}
                <span class="ml-1 text-base font-semibold text-slate-400">Sistema corredizo</span>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="imprimirTodo"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                <i class="fa-solid fa-print"></i> Imprimir plano
            </button>
            <button type="button" wire:click="agregarMampara"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 active:scale-95">
                <i class="fa-solid fa-plus"></i> Nueva mampara
            </button>
            <button type="button" wire:click="limpiarMamparas" wire:confirm="¿Vaciar todas las mamparas?"
                class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-bold text-red-600 transition hover:bg-red-50 active:scale-95">
                <i class="fa-solid fa-trash"></i> Vaciar
            </button>
        </div>
    </div>

    {{-- PESTAÑAS --}}
    <div class="mb-5 flex items-center gap-2 overflow-x-auto pb-1">
        @foreach ($mamparas as $index => $v)
            <div class="group relative shrink-0" wire:key="mampara-tab-{{ $index }}">
                <button type="button" wire:click="cambiarMampara({{ $index }})"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2 text-xs font-extrabold uppercase tracking-wide transition
                    {{ $mamparaActiva == $index
                        ? 'bg-slate-800 text-white shadow-md'
                        : 'bg-white text-slate-500 ring-1 ring-slate-200 hover:text-indigo-600 hover:ring-indigo-300' }}">
                    <i class="fa-solid fa-table-cells-large"></i> {{ $v['nombre'] }}
                </button>

                @if (count($mamparas) > 1)
                    <button type="button" wire:click.stop="eliminarMampara({{ $index }})" title="Eliminar"
                        class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow transition lg:opacity-0 lg:group-hover:opacity-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- RESUMEN --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="{{ $tarjeta }} p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Medida ingresada</p>
            <p class="mt-1 font-mono text-xl font-bold text-slate-800">
                {{ number_format($ancho, 1) }} × {{ number_format($alto, 1) }}
                <span class="text-xs font-semibold text-slate-400">cm</span>
            </p>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Medida fabricación</p>
            <p class="mt-1 font-mono text-xl font-bold text-indigo-700">
                {{ number_format($this->anchoFabricacion, 1) }} × {{ number_format($this->altoFabricacion, 1) }}
                <span class="text-xs font-semibold">cm</span>
            </p>
            <p class="text-[10px] font-semibold text-indigo-400">Descuento inicial −0.5 × −0.5 cm</p>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-400">Área de vidrio</p>
            <p class="mt-1 font-mono text-xl font-bold text-sky-700">
                {{ number_format($pl['areaM2'], 2) }} <span class="text-xs font-semibold">m²</span>
            </p>
            <p class="text-[10px] font-semibold text-sky-400">Perímetro {{ number_format($pl['perimM'], 2) }} m</p>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Hojas</p>
            <p class="mt-1 font-mono text-xl font-bold text-emerald-700">
                {{ $this->totalHojas }} <span class="text-xs font-semibold">en total</span>
            </p>
            <p class="text-[10px] font-semibold text-emerald-500">{{ $fijos }} fijos · {{ $corredizas }}
                corredizas</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">

        {{-- ===================== COLUMNA IZQUIERDA ===================== --}}
        <div class="space-y-5 xl:col-span-4">

            {{-- MEDIDAS --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="mb-4 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <i class="fa-solid fa-ruler-combined text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Medidas</h2>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Ancho</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" inputmode="decimal"
                                wire:model.lazy="ancho" class="{{ $inputClase }}">
                            <span
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Alto</label>
                        <div class="relative">
                            <input type="number" step="0.1" min="0" inputmode="decimal"
                                wire:model.lazy="alto" class="{{ $inputClase }}">
                            <span
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                        </div>
                    </div>
                </div>

                {{-- SOBRELUZ --}}
                <div class="mt-4 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
                    <label class="flex cursor-pointer select-none items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <i class="fa-solid fa-window-maximize text-slate-400"></i>
                            Con sobreluz
                        </span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" wire:model.live="conSobreluz" class="peer sr-only">
                            <span
                                class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-200"></span>
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    @if ($conSobreluz)
                        <div class="mt-3">
                            <label
                                class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Puente
                                (banda de hojas)</label>
                            <div class="relative">
                                <input type="number" step="0.1" min="0" inputmode="decimal"
                                    wire:model.lazy="puente" class="{{ $inputClase }}">
                                <span
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                            </div>
                            <p
                                class="mt-2 rounded-lg bg-white px-3 py-2 font-mono text-[11px] leading-snug text-slate-500 ring-1 ring-slate-200">
                                Sobreluz = {{ number_format($this->altoFabricacion, 1) }} −
                                {{ number_format($puente, 1) }}
                                = <b class="text-indigo-600">{{ number_format($this->sobreluzAltura, 1) }} cm</b>
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COMPOSICIÓN --}}
            <div class="{{ $tarjeta }} p-5">
                <div class="mb-4 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <i class="fa-solid fa-table-columns text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Composición</h2>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['corredizas', 'Corredizas', $corredizas], ['fijos', 'Fijos', $fijos]] as [$campo, $etiqueta, $valor])
                        <div>
                            <label
                                class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $etiqueta }}</label>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                    wire:click="$set('{{ $campo }}', {{ max(0, $valor - 1) }})"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <input type="number" min="0" inputmode="numeric"
                                    wire:model.lazy="{{ $campo }}"
                                    class="w-full min-w-0 rounded-xl border border-slate-300 bg-white px-2 py-2.5 text-center text-sm font-bold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none">
                                <button type="button"
                                    wire:click="$set('{{ $campo }}', {{ $valor + 1 }})"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 active:scale-95">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Distribución --}}
                <div class="mt-4">
                    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">Distribución de la
                        fila</p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ($this->distribucionHojas as $tipo)
                            <span
                                class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg px-2 font-mono text-sm font-extrabold
                                {{ $tipo === 'C' ? 'bg-rose-100 text-rose-700 ring-1 ring-rose-200' : 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200' }}">
                                {{ $tipo }}
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        <b class="text-indigo-600">F</b> fijo · <b class="text-rose-600">C</b> corredizo
                        @if ($pl['haySobreluz'])
                            · sobreluz fijo arriba ({{ number_format($pl['Puente'], 1) }} cm)
                        @endif
                    </p>
                </div>

                {{-- Vidrio --}}
                <div class="mt-4">
                    <label
                        class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Vidrio</label>
                    <select wire:model.live="vidrio"
                        class="w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 focus:outline-none">
                        <option value="simple">Simple 6mm</option>
                        <option value="doble">Templado 6mm</option>
                        <option value="dvh">DVH 6+12+6mm</option>
                    </select>
                </div>
            </div>

            {{-- VISTA Y OPCIONES --}}
            <div class="{{ $tarjeta }} p-3">
                <div class="mb-1 flex items-center gap-2 px-2 pt-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-sliders text-sm"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700">Vista y opciones</h2>
                </div>

                @foreach ($opciones as [$clave, $texto, $icono])
                    <label
                        class="flex cursor-pointer select-none items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-slate-50">
                        <span class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
                            <i class="fa-solid {{ $icono }} w-4 text-center text-slate-400"></i>
                            {{ $texto }}
                        </span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" wire:model.live="{{ $clave }}" class="peer sr-only">
                            <span
                                class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-200"></span>
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ===================== COLUMNA DERECHA: PLANO ===================== --}}
        <section x-data="{ mostrarPlano: true }" class="{{ $tarjeta }} h-fit overflow-hidden xl:col-span-8">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800 font-mono text-sm font-bold text-white">C</span>
                    <div>
                        <h2 class="text-base font-extrabold tracking-tight text-slate-800">Plano de mampara</h2>
                        <p class="text-[11px] text-slate-400">Elevación frontal · cotas en cm / mm</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 font-mono text-[11px] font-semibold text-slate-600">{{ $pl['fecha'] }}</span>
                    <span
                        class="rounded-full bg-indigo-50 px-3 py-1 font-mono text-[11px] font-semibold text-indigo-600">Escala
                        1:{{ $pl['escala'] }}</span>
                    <button type="button" @click="mostrarPlano = !mostrarPlano"
                        class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white transition hover:bg-slate-700 active:scale-95">
                        <i class="fa-solid" :class="mostrarPlano ? 'fa-eye-slash' : 'fa-eye'"></i>
                        <span x-text="mostrarPlano ? 'Ocultar' : 'Mostrar'"></span>
                    </button>
                </div>
            </div>

            <div x-show="mostrarPlano" x-cloak class="bg-[#F8FAFC] p-3 sm:p-4">
                <div class="flex w-full items-center justify-center overflow-x-auto">

                    @php
                        $p = $this->plano;

                        $ink = '#1E293B';
                        $dim = '#C2410C';
                        $acc = '#4F46E5';

                        $x0 = $p['x0'];
                        $y0 = $p['y0'];
                        $W = $p['w'];
                        $H = $p['h'];
                        $fm = $p['fm'];
                        $fh = $p['fh'];
                        $xR = $x0 + $W;
                        $yB = $y0 + $H;

                        // ancho de una "pastilla" de texto
                        $pw = fn($t, $fs = 6.2) => mb_strlen($t) * $fs + 18;

                        // COTA HORIZONTAL (extensiones + marcas oblicuas + pastilla)
                        $dimH = function ($xa, $xb, $y, $yRef, $txt, $fs = 12) use ($dim) {
                            $m = ($xa + $xb) / 2;
                            $w = mb_strlen($txt) * $fs * 0.6 + 18;
                            $e = $yRef > $y ? -6 : 6;
                            return '<g fill="none" stroke="' .
                                $dim .
                                '" stroke-width="1">' .
                                '<line x1="' .
                                $xa .
                                '" y1="' .
                                $yRef .
                                '" x2="' .
                                $xa .
                                '" y2="' .
                                ($y + $e) .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xb .
                                '" y1="' .
                                $yRef .
                                '" x2="' .
                                $xb .
                                '" y2="' .
                                ($y + $e) .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xa .
                                '" y1="' .
                                $y .
                                '" x2="' .
                                $xb .
                                '" y2="' .
                                $y .
                                '"/>' .
                                '<line x1="' .
                                ($xa - 5) .
                                '" y1="' .
                                ($y + 5) .
                                '" x2="' .
                                ($xa + 5) .
                                '" y2="' .
                                ($y - 5) .
                                '" stroke-width="1.8"/>' .
                                '<line x1="' .
                                ($xb - 5) .
                                '" y1="' .
                                ($y + 5) .
                                '" x2="' .
                                ($xb + 5) .
                                '" y2="' .
                                ($y - 5) .
                                '" stroke-width="1.8"/>' .
                                '</g>' .
                                '<rect x="' .
                                ($m - $w / 2) .
                                '" y="' .
                                ($y - 10) .
                                '" width="' .
                                $w .
                                '" height="20" rx="10" fill="#fff" stroke="' .
                                $dim .
                                '" stroke-width=".8"/>' .
                                '<text x="' .
                                $m .
                                '" y="' .
                                ($y + 4) .
                                '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' .
                                $fs .
                                '" font-weight="500" fill="' .
                                $dim .
                                '">' .
                                e($txt) .
                                '</text>';
                        };

                        // COTA VERTICAL
                        $dimV = function ($x, $ya, $yb, $xRef, $txt, $fs = 12) use ($dim) {
                            $m = ($ya + $yb) / 2;
                            $w = mb_strlen($txt) * $fs * 0.6 + 18;
                            $e = $xRef > $x ? -6 : 6;
                            return '<g fill="none" stroke="' .
                                $dim .
                                '" stroke-width="1">' .
                                '<line x1="' .
                                $xRef .
                                '" y1="' .
                                $ya .
                                '" x2="' .
                                ($x + $e) .
                                '" y2="' .
                                $ya .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $xRef .
                                '" y1="' .
                                $yb .
                                '" x2="' .
                                ($x + $e) .
                                '" y2="' .
                                $yb .
                                '" stroke-opacity=".45"/>' .
                                '<line x1="' .
                                $x .
                                '" y1="' .
                                $ya .
                                '" x2="' .
                                $x .
                                '" y2="' .
                                $yb .
                                '"/>' .
                                '<line x1="' .
                                ($x - 5) .
                                '" y1="' .
                                ($ya + 5) .
                                '" x2="' .
                                ($x + 5) .
                                '" y2="' .
                                ($ya - 5) .
                                '" stroke-width="1.8"/>' .
                                '<line x1="' .
                                ($x - 5) .
                                '" y1="' .
                                ($yb + 5) .
                                '" x2="' .
                                ($x + 5) .
                                '" y2="' .
                                ($yb - 5) .
                                '" stroke-width="1.8"/>' .
                                '</g>' .
                                '<g transform="rotate(-90 ' .
                                $x .
                                ' ' .
                                $m .
                                ')">' .
                                '<rect x="' .
                                ($x - $w / 2) .
                                '" y="' .
                                ($m - 10) .
                                '" width="' .
                                $w .
                                '" height="20" rx="10" fill="#fff" stroke="' .
                                $dim .
                                '" stroke-width=".8"/>' .
                                '<text x="' .
                                $x .
                                '" y="' .
                                ($m + 4) .
                                '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' .
                                $fs .
                                '" font-weight="500" fill="' .
                                $dim .
                                '">' .
                                e($txt) .
                                '</text>' .
                                '</g>';
                        };

                        // ETIQUETA CON LÍNEA GUÍA
                        // modo 'l' = pastilla a la izquierda del punto final (x,y)
                        // modo 't' = pastilla arriba, su borde izquierdo en x
                        // modo 'te' = pastilla arriba, su borde derecho en x
                        $call = function ($px, $py, $txt, $x, $y, $mode = 'l') use ($acc) {
                            $w = mb_strlen($txt) * 6.2 + 18;
                            if ($mode === 'l') {
                                $rx = $x - $w;
                                $ex = $x;
                                $ey = $y;
                            } elseif ($mode === 't') {
                                $rx = $x;
                                $ex = $x + $w / 2;
                                $ey = $y + 11;
                            } else {
                                $rx = $x - $w;
                                $ex = $x - $w / 2;
                                $ey = $y + 11;
                            }
                            return '<line x1="' .
                                $px .
                                '" y1="' .
                                $py .
                                '" x2="' .
                                $ex .
                                '" y2="' .
                                $ey .
                                '" stroke="' .
                                $acc .
                                '" stroke-width="1" stroke-opacity=".7"/>' .
                                '<circle cx="' .
                                $px .
                                '" cy="' .
                                $py .
                                '" r="3" fill="#fff" stroke="' .
                                $acc .
                                '" stroke-width="1.5"/>' .
                                '<rect x="' .
                                $rx .
                                '" y="' .
                                ($y - 11) .
                                '" width="' .
                                $w .
                                '" height="22" rx="11" fill="' .
                                $acc .
                                '"/>' .
                                '<text x="' .
                                ($rx + $w / 2) .
                                '" y="' .
                                ($y + 4) .
                                '" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#fff">' .
                                e($txt) .
                                '</text>';
                        };

                        // primer paño fijo y primera hoja corrediza (para anclar etiquetas)
                        $pFijo = collect($p['panels'])->first(fn($q) => !$q['esCorrediza']);
                        $pCorr = collect($p['panels'])->first(fn($q) => $q['esCorrediza']);

                        $bandaHojas = max(0, $p['altoFabricacion'] - ($p['haySobreluz'] ? $p['Puente'] : 0));
                    @endphp

                    <svg viewBox="0 0 1100 760" xmlns="http://www.w3.org/2000/svg" width="100%"
                        preserveAspectRatio="xMidYMid meet"
                        style="display:block;width:100%;height:auto;min-height:500px;">

                        <defs>
                            {{-- fondo tipo papel milimetrado --}}
                            <pattern id="gridSmall" width="20" height="20" patternUnits="userSpaceOnUse">
                                <path d="M20 0H0V20" fill="none" stroke="#CBD5E1" stroke-opacity=".35"
                                    stroke-width=".6" />
                            </pattern>

                            {{-- pared / piso --}}
                            <pattern id="hatchWall" width="8" height="8" patternUnits="userSpaceOnUse"
                                patternTransform="rotate(-45)">
                                <line x1="0" y1="0" x2="0" y2="8" stroke="#94A3B8"
                                    stroke-opacity=".55" stroke-width="1" />
                            </pattern>

                            {{-- aluminio --}}
                            <linearGradient id="alu" x1="0" y1="0" x2="1"
                                y2="1">
                                <stop offset="0" stop-color="#F1F5F9" />
                                <stop offset=".45" stop-color="#CBD5E1" />
                                <stop offset="1" stop-color="#94A3B8" />
                            </linearGradient>
                            <linearGradient id="aluLeaf" x1="0" y1="0" x2="0"
                                y2="1">
                                <stop offset="0" stop-color="#F8FAFC" />
                                <stop offset="1" stop-color="#B6C2D1" />
                            </linearGradient>

                            {{-- vidrio --}}
                            <linearGradient id="glass" x1="0" y1="0" x2="1"
                                y2="1">
                                <stop offset="0" stop-color="#DFF0FB" />
                                <stop offset=".55" stop-color="#BBDDF2" />
                                <stop offset="1" stop-color="#9CC9E6" />
                            </linearGradient>

                            {{-- malla del mosquitero --}}
                            <pattern id="mesh" width="5" height="5" patternUnits="userSpaceOnUse">
                                <path d="M0 0H5M0 0V5" stroke="#475569" stroke-opacity=".35" stroke-width=".5"
                                    fill="none" />
                            </pattern>

                            <marker id="slideArrow" markerWidth="10" markerHeight="10" refX="8"
                                refY="5" orient="auto">
                                <path d="M1 1 L8 5 L1 9 Z" fill="#334155" fill-opacity=".7" />
                            </marker>

                            <filter id="softShadow" x="-10%" y="-10%" width="120%" height="125%">
                                <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#0F172A"
                                    flood-opacity=".18" />
                            </filter>
                        </defs>

                        {{-- FONDO --}}
                        <rect width="1100" height="760" fill="#F8FAFC" />
                        <rect width="1100" height="760" fill="url(#gridSmall)" />

                        {{-- PARED (vano) Y PISO --}}
                        <rect x="{{ $x0 - 16 }}" y="{{ $y0 - 16 }}" width="{{ $W + 32 }}"
                            height="{{ $H + 16 }}" fill="#EEF2F6" />
                        <rect x="{{ $x0 - 16 }}" y="{{ $y0 - 16 }}" width="{{ $W + 32 }}"
                            height="{{ $H + 16 }}" fill="url(#hatchWall)" />
                        <rect x="{{ $x0 - 50 }}" y="{{ $yB }}" width="{{ $W + 100 }}"
                            height="8" fill="url(#hatchWall)" />
                        <line x1="{{ $x0 - 50 }}" y1="{{ $yB }}" x2="{{ $xR + 50 }}"
                            y2="{{ $yB }}" stroke="{{ $ink }}" stroke-width="2.2" />

                        {{-- MARCO EXTERIOR (anillo de aluminio) --}}
                        <g filter="url(#softShadow)">
                            <path fill-rule="evenodd" fill="url(#alu)" stroke="{{ $ink }}"
                                stroke-width="1.8"
                                d="M{{ $x0 }} {{ $y0 }}h{{ $W }}v{{ $H }}h-{{ $W }}Z
               M{{ $p['innerX'] }} {{ $p['innerY'] }}h{{ $p['innerW'] }}v{{ $p['innerH'] }}h-{{ $p['innerW'] }}Z" />
                        </g>
                        <rect x="{{ $x0 + $fm * 0.3 }}" y="{{ $y0 + $fm * 0.3 }}"
                            width="{{ max(1, $W - $fm * 0.6) }}" height="{{ max(1, $H - $fm * 0.6) }}"
                            fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="1" />

                        {{-- SOBRELUZ --}}
                        @if ($p['haySobreluz'])
                            @php
                                $slx = $p['innerX'] + $fh;
                                $sly = $p['innerY'] + $fh;
                                $slw = max(2, $p['innerW'] - $fh * 2);
                                $slh = max(2, $p['sobreluzH'] - $fh * 2);
                            @endphp
                            <path fill-rule="evenodd" fill="url(#aluLeaf)" stroke="{{ $ink }}"
                                stroke-width="1.3"
                                d="M{{ $p['innerX'] }} {{ $p['innerY'] }}h{{ $p['innerW'] }}v{{ $p['sobreluzH'] }}h-{{ $p['innerW'] }}Z
               M{{ $slx }} {{ $sly }}h{{ $slw }}v{{ $slh }}h-{{ $slw }}Z" />
                            <rect x="{{ $slx }}" y="{{ $sly }}" width="{{ $slw }}"
                                height="{{ $slh }}" fill="url(#glass)" stroke="#2C5F8A"
                                stroke-opacity=".55" stroke-width="1" />
                            <path
                                d="M{{ $slx + $slw * 0.12 }} {{ $sly + $slh }} L{{ $slx + $slw * 0.2 }} {{ $sly }} L{{ $slx + $slw * 0.27 }} {{ $sly }} L{{ $slx + $slw * 0.19 }} {{ $sly + $slh }}Z"
                                fill="#fff" fill-opacity=".45" />
                            <path
                                d="M{{ $slx + $slw * 0.3 }} {{ $sly + $slh }} L{{ $slx + $slw * 0.33 }} {{ $sly }} L{{ $slx + $slw * 0.35 }} {{ $sly }} L{{ $slx + $slw * 0.32 }} {{ $sly + $slh }}Z"
                                fill="#fff" fill-opacity=".35" />

                            {{-- travesaño --}}
                            <rect x="{{ $p['innerX'] }}" y="{{ $p['innerY'] + $p['sobreluzH'] }}"
                                width="{{ $p['innerW'] }}" height="{{ $p['travesanoPx'] }}" fill="url(#alu)"
                                stroke="{{ $ink }}" stroke-width="1.4" />
                        @endif

                        {{-- HOJAS: primero fijas, después corredizas (quedan por encima, como en obra) --}}
                        @foreach ([false, true] as $pasada)
                            @foreach ($p['panels'] as $i => $panel)
                                @continue($panel['esCorrediza'] !== $pasada)

                                @php
                                    $leafH = max($p['filaH'], 3);
                                    $gcx = $panel['gx'] + $panel['gw'] / 2;
                                    $gcy = $panel['gy'] + $panel['gh'] / 2;
                                @endphp

                                <g @if ($panel['esCorrediza']) filter="url(#softShadow)" @endif>
                                    <path fill-rule="evenodd" fill="url(#aluLeaf)" stroke="{{ $ink }}"
                                        stroke-width="{{ $panel['esCorrediza'] ? 1.6 : 1.3 }}"
                                        d="M{{ $panel['hx'] }} {{ $p['filaY'] }}h{{ $panel['hw'] }}v{{ $leafH }}h-{{ $panel['hw'] }}Z
                       M{{ $panel['gx'] }} {{ $panel['gy'] }}h{{ $panel['gw'] }}v{{ $panel['gh'] }}h-{{ $panel['gw'] }}Z" />
                                </g>

                                {{-- vidrio + reflejos --}}
                                <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}" width="{{ $panel['gw'] }}"
                                    height="{{ $panel['gh'] }}" fill="url(#glass)" stroke="#2C5F8A"
                                    stroke-opacity=".55" stroke-width="1" />
                                <path
                                    d="M{{ $panel['gx'] + $panel['gw'] * 0.12 }} {{ $panel['gy'] + $panel['gh'] }} L{{ $panel['gx'] + $panel['gw'] * 0.45 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.55 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.22 }} {{ $panel['gy'] + $panel['gh'] }}Z"
                                    fill="#fff" fill-opacity=".40" />
                                <path
                                    d="M{{ $panel['gx'] + $panel['gw'] * 0.6 }} {{ $panel['gy'] + $panel['gh'] }} L{{ $panel['gx'] + $panel['gw'] * 0.72 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.76 }} {{ $panel['gy'] }} L{{ $panel['gx'] + $panel['gw'] * 0.64 }} {{ $panel['gy'] + $panel['gh'] }}Z"
                                    fill="#fff" fill-opacity=".28" />

                                @if ($panel['esCorrediza'])
                                    {{-- flecha de desplazamiento (alterna sentido) --}}
                                    @php
                                        $s = min(34, $panel['gw'] * 0.28);
                                        $dir = $i % 2 === 0 ? 1 : -1;
                                    @endphp
                                    <line x1="{{ $gcx - $s * $dir }}" y1="{{ $gcy }}"
                                        x2="{{ $gcx + $s * $dir }}" y2="{{ $gcy }}" stroke="#334155"
                                        stroke-opacity=".7" stroke-width="2.2" stroke-linecap="round"
                                        marker-end="url(#slideArrow)" />

                                    {{-- tirador --}}
                                    <rect x="{{ $panel['handleX'] }}" y="{{ $panel['handleY'] }}" width="6"
                                        height="30" rx="3" fill="#BE123C" stroke="#fff"
                                        stroke-width="1" />

                                    {{-- mosquitero --}}
                                    @if ($mosquitero)
                                        <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}"
                                            width="{{ $panel['gw'] }}" height="{{ $panel['gh'] }}"
                                            fill="url(#mesh)" />
                                        <rect x="{{ $panel['hx'] + 4 }}" y="{{ $p['filaY'] + 4 }}"
                                            width="{{ max($panel['hw'] - 8, 2) }}"
                                            height="{{ max($p['filaH'] - 8, 2) }}" fill="none" stroke="#475569"
                                            stroke-width="1" stroke-dasharray="4 3" />
                                    @endif
                                @endif
                            @endforeach
                        @endforeach

                        {{-- RIELES --}}
                        @if ($p['nCorredizas'] > 0)
                            <line x1="{{ $x0 }}" y1="{{ $p['railY1'] }}" x2="{{ $xR }}"
                                y2="{{ $p['railY1'] }}" stroke="#475569" stroke-width="1.2"
                                stroke-dasharray="6 4" />
                            <line x1="{{ $x0 }}" y1="{{ $p['railY2'] }}" x2="{{ $xR }}"
                                y2="{{ $p['railY2'] }}" stroke="#475569" stroke-width="1.2"
                                stroke-dasharray="6 4" />
                        @endif

                        {{-- COTAS POR HOJA --}}
                        @if ($mostrarCotaHoja)
                            @foreach ($p['panels'] as $panel)
                                {!! $dimH(
                                    $panel['hx'],
                                    $panel['hx'] + $panel['hw'],
                                    $yB + 28,
                                    $p['filaY'] + $p['filaH'],
                                    $panel['cotaLabel'],
                                    10,
                                ) !!}
                            @endforeach
                        @endif

                        {{-- COTAS GENERALES --}}
                        @if ($mostrarCotas)
                            {!! $dimH(
                                $x0,
                                $xR,
                                $y0 - 52,
                                $y0 - 20,
                                number_format($p['Wcm'], 1) . ' cm  ·  ' . number_format($p['Wcm'] * 10, 0) . ' mm',
                                13,
                            ) !!}
                            {!! $dimH($x0, $xR, $y0 - 28, $y0 - 20, 'FAB ' . number_format($p['anchoFabricacion'], 1) . ' cm', 10) !!}

                            {!! $dimV(
                                $x0 - 44,
                                $y0,
                                $yB,
                                $x0 - 20,
                                number_format($p['Hcm'], 1) . ' cm  ·  ' . number_format($p['Hcm'] * 10, 0) . ' mm',
                                13,
                            ) !!}

                            @if ($p['haySobreluz'])
                                {!! $dimV(
                                    $xR + 44,
                                    $p['innerY'],
                                    $p['innerY'] + $p['sobreluzH'],
                                    $xR + 20,
                                    'SL ' . number_format($p['Puente'], 1),
                                    11,
                                ) !!}
                                {!! $dimV(
                                    $xR + 44,
                                    $p['filaY'],
                                    $p['filaY'] + $p['filaH'],
                                    $xR + 20,
                                    'HOJAS ' . number_format($bandaHojas, 1),
                                    11,
                                ) !!}
                            @endif
                        @endif

                        {{-- ETIQUETAS DE PARTES --}}
                        @if ($mostrarEtiquetas)
                            {{-- fila superior --}}
                            {!! $call($x0 + $W * 0.04, $y0 + $fm / 2, 'Marco superior', $x0 + 10, $y0 - 80, 'te') !!}

                            @if ($p['haySobreluz'])
                                {!! $call(
                                    $p['innerX'] + $p['innerW'] / 2,
                                    $p['innerY'] + $p['sobreluzH'] / 2,
                                    'Sobreluz fijo',
                                    $x0 + 30,
                                    $y0 - 80,
                                    't',
                                ) !!}
                            @endif

                            @if ($pCorr)
                                {!! $call(
                                    $pCorr['gx'] + $pCorr['gw'] / 2,
                                    $p['filaY'] + $p['filaH'] * 0.22,
                                    'Hoja corrediza',
                                    $x0 + 160,
                                    $y0 - 80,
                                    't',
                                ) !!}
                            @endif

                            {{-- lateral izquierdo --}}
                            @if ($pFijo)
                                {!! $call(
                                    $pFijo['gx'] + $pFijo['gw'] / 2,
                                    $p['filaY'] + $p['filaH'] * 0.35,
                                    'Paño fijo',
                                    $x0 - 62,
                                    $y0 + $H * 0.27,
                                    'l',
                                ) !!}
                            @endif

                            {!! $call($x0 + $fm / 2, $y0 + $H * 0.5, 'Jamba lateral', $x0 - 62, $y0 + $H * 0.5, 'l') !!}

                            @if ($p['nCorredizas'] > 0)
                                {!! $call($x0 + $W * 0.1, $p['railY1'], 'Riel corredizo', $x0 - 62, $y0 + $H * 0.73, 'l') !!}
                            @endif
                        @endif

                        {{-- CARTUCHO --}}
                        @php
                            $tbX = 70;
                            $tbY = 616;
                            $tbW = 960;
                            $tbH = 124;
                            $cols = [
                                $tbX + 26,
                                $tbX + 26 + $tbW * 0.25,
                                $tbX + 26 + $tbW * 0.5,
                                $tbX + 26 + $tbW * 0.75,
                            ];
                            $celdas = [
                                [0, 18, 'SISTEMA', 'Corredizo · Mampara'],
                                [
                                    0,
                                    56,
                                    'DIMENSIÓN',
                                    number_format($p['Wcm'], 1) . ' x ' . number_format($p['Hcm'], 1) . ' cm',
                                ],
                                [
                                    0,
                                    94,
                                    'FABRICACIÓN',
                                    number_format($p['anchoFabricacion'], 1) .
                                    ' x ' .
                                    number_format($p['altoFabricacion'], 1) .
                                    ' cm',
                                ],
                                [1, 18, 'N° HOJAS', $p['numHojas'] . ' hojas'],
                                [1, 56, 'VIDRIO', $p['vidrioLabel']],
                                [1, 94, 'MOSQUITERO', $mosquitero ? 'Sí' : 'No'],
                                [2, 18, 'PERFIL MARCO', '45 mm'],
                                [2, 56, 'PERFIL HOJA', '40 mm'],
                                [2, 94, 'ESCALA', '1:' . $p['escala']],
                                [
                                    3,
                                    18,
                                    'PLANO',
                                    'SC-' . number_format($p['Wcm'], 0) . 'x' . number_format($p['Hcm'], 0),
                                ],
                                [3, 56, 'FECHA', $p['fecha']],
                                [3, 94, 'REVISIÓN', 'A'],
                            ];
                        @endphp

                        <rect x="{{ $tbX }}" y="{{ $tbY }}" width="{{ $tbW }}"
                            height="{{ $tbH }}" rx="10" fill="#fff" stroke="#CBD5E1"
                            stroke-width="1" />
                        <rect x="{{ $tbX }}" y="{{ $tbY }}" width="8"
                            height="{{ $tbH }}" rx="4" fill="{{ $acc }}" />

                        @foreach ([1, 2, 3] as $k)
                            <line x1="{{ $cols[$k] - 16 }}" y1="{{ $tbY + 14 }}" x2="{{ $cols[$k] - 16 }}"
                                y2="{{ $tbY + $tbH - 14 }}" stroke="#E2E8F0" stroke-width="1" />
                        @endforeach

                        @foreach ($celdas as [$c, $dy, $label, $value])
                            <text x="{{ $cols[$c] }}" y="{{ $tbY + $dy + 10 }}"
                                font-family="JetBrains Mono, monospace" font-size="9.5" fill="#94A3B8"
                                letter-spacing="1">{{ $label }}</text>
                            <text x="{{ $cols[$c] }}" y="{{ $tbY + $dy + 27 }}" font-family="Inter, sans-serif"
                                font-size="13" font-weight="700"
                                fill="{{ $ink }}">{{ $value }}</text>
                        @endforeach
                    </svg>

                </div>
            </div>

            <div x-show="!mostrarPlano" x-cloak class="py-16 text-center text-sm italic text-slate-400">
                Plano oculto
            </div>
        </section>
    </div>

    {{-- ===================== CORTES Y PERFILES ===================== --}}
    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">

        {{-- CORTE DE VIDRIOS --}}
        <div class="{{ $tarjeta }} overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-sky-600 to-indigo-600 px-5 py-4 text-white">
                <div class="flex items-center gap-2 font-bold">
                    <i class="fa-solid fa-gem"></i> Corte de vidrios
                </div>
                <div class="flex items-center gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-white/20 px-3 py-1">{{ count($this->cortesVidrio) }} piezas</span>
                    <span class="rounded-full bg-white/20 px-3 py-1">{{ $pl['vidrioLabel'] }}</span>
                </div>
            </div>

            <div
                class="grid grid-cols-[1fr_auto_auto_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Hoja</span>
                <span>Tipo</span>
                <span class="w-20 text-right">Ancho</span>
                <span class="w-20 text-right">Alto</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($this->cortesVidrio as $corte)
                    <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-4 px-5 py-3 transition hover:bg-indigo-50/50"
                        wire:key="corte-{{ $loop->index }}">
                        <span class="text-sm font-bold text-slate-800">{{ $corte['hoja'] }}</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide
                            {{ $corte['tipo'] === 'Fijo' ? 'bg-indigo-100 text-indigo-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $corte['tipo'] }}
                        </span>
                        <span
                            class="w-20 text-right font-mono text-xs font-bold text-slate-700">{{ number_format($corte['ancho'], 1) }}
                            cm</span>
                        <span
                            class="w-20 text-right font-mono text-xs font-bold text-slate-700">{{ number_format($corte['alto'], 1) }}
                            cm</span>
                    </div>
                @empty
                    <div class="py-12 text-center text-sm italic text-slate-400">Agrega hojas para ver los cortes</div>
                @endforelse
            </div>

            <details class="border-t border-slate-200 px-5 py-3 text-[11px] leading-relaxed text-slate-500">
                <summary class="cursor-pointer select-none font-bold uppercase tracking-wider text-slate-500">
                    Descuentos aplicados</summary>
                <ul class="mt-2 space-y-1">
                    <li><b class="text-slate-700">Primero:</b> ancho − 0.5 cm y alto − 0.5 cm.</li>
                    <li><b class="text-indigo-600">Fijo:</b> ancho hoja − 4 cm · alto de banda − 5.5 cm.</li>
                    <li><b class="text-rose-600">Corredizo:</b> ancho hoja + 4 cm · alto de banda − 6.5 cm.</li>
                    <li><b class="text-slate-700">Sobreluz:</b> ancho fabricación − 4 cm · altura de sobreluz − 5.5 cm.
                    </li>
                </ul>
            </details>
        </div>

        {{-- PERFILES --}}
        <div class="{{ $tarjeta }} overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-4 text-white">
                <div class="flex items-center gap-2 font-bold">
                    <i class="fa-solid fa-list-check"></i> Perfiles y accesorios
                </div>
                <span
                    class="rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold">{{ count($this->perfilesMampara) }}
                    ítems</span>
            </div>

            <div
                class="grid grid-cols-[1fr_auto_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span>Detalle</span>
                <span>Medida</span>
                <span class="w-9 text-center">Cant.</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($this->perfilesMampara as $perfil)
                    <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4 px-5 py-3 transition hover:bg-indigo-50/50"
                        wire:key="perfil-{{ $loop->index }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="rounded-md bg-indigo-50 px-2 py-1 font-mono text-[11px] font-bold text-indigo-600">{{ $perfil['codigo'] }}</span>
                            <span class="truncate text-sm font-bold text-slate-800">{{ $perfil['nombre'] }}</span>
                        </div>
                        <span
                            class="whitespace-nowrap rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-xs font-bold text-slate-700">
                            {{ number_format($perfil['medida'], 1) }} cm
                        </span>
                        <span
                            class="flex h-8 w-9 items-center justify-center rounded-lg bg-emerald-100 font-mono text-xs font-extrabold text-emerald-700">
                            {{ $perfil['cant'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="border-t border-slate-200 px-5 py-3 text-[11px] leading-relaxed text-slate-500">
                7955, 5283 y 8413 usan la medida de fabricación:
                <b class="text-slate-700">{{ number_format($this->anchoFabricacion, 1) }} cm</b>
                (el descuento inicial de 0.5 cm ya está aplicado).
            </p>
        </div>
    </div>

    <p class="mt-5 text-center text-[11px] text-slate-400">
        Plano referencial de fabricación · perfiles y cotas aproximados al sistema de mamparas correderas
        de aluminio "Corredizo" · verificar con proveedor antes de ejecutar la obra.
    </p>

</div>
</div>
