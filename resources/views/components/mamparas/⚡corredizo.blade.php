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

        return [
            ['codigo' => '7955', 'nombre' => 'U de 3/4', 'medida' => $W, 'cant' => 1],
            ['codigo' => '5283', 'nombre' => 'Tubo multifuncional', 'medida' => $W, 'cant' => 1],
            ['codigo' => '8413', 'nombre' => 'Riel baja', 'medida' => $W, 'cant' => 1],
            ['codigo' => '3003', 'nombre' => 'U de 1/2', 'medida' => $P / 2 + 4.2, 'cant' => max(0, $this->fijos)],
            ['codigo' => '4104', 'nombre' => 'Ángulo de 1/2 económico', 'medida' => max(0, $P - 0.3), 'cant' => max(0, $this->fijos)],
            ['codigo' => '8220', 'nombre' => 'H 6 mm', 'medida' => $P / 2 + 3.6, 'cant' => max(0, $this->corredizas)],
        ];
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
        $travesanoPx = $haySobreluz ? max(5, $fm * 0.60) : 0;
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

            $leftOverlap = ($esCorrediza && $i > 0) ? $ov / 2 : 0;
            $rightOverlap = ($esCorrediza && $i < $n - 1) ? $ov / 2 : 0;

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

        $this->mamparas[] = array_merge(
            ['nombre' => 'M - ' . (count($this->mamparas) + 1)],
            $this->snapshotMampara(),
        );

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
            array_merge(['nombre' => 'M - 1'], [
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
            ]),
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
        if (in_array($property, [
            'ancho', 'alto', 'conSobreluz', 'puente', 'corredizas', 'fijos', 'datos',
            'vidrio', 'mosquitero', 'mostrarCotas', 'mostrarCotaHoja', 'mostrarEtiquetas',
        ], true)) {
            $this->guardarMamparaActual();
        }
    }
};
?>

<div class="nova-plano p-4 lg:p-6">

    <style>
        .nova-plano {
            --paper: #FAF9F6;
            --panel: #FFFFFF;
            --panel-soft: #F1EEE6;
            --line: #DCD5C7;
            --ink: #23282D;
            --ink-dim: #6B7280;
            --accent: #B23A2E;
            --blue-accent: #2C5F8A;
            --glass: rgba(44, 95, 138, 0.06);
            --danger: #C0392B;

            background: var(--paper);
            color: var(--ink);
            border-radius: 1rem;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .nova-plano .font-display { font-family: 'Space Grotesk', 'Inter', sans-serif; }
        .nova-plano .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        .nova-plano .grid-bg {
            background-image:
                linear-gradient(rgba(35, 40, 45, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(35, 40, 45, 0.06) 1px, transparent 1px);
            background-size: 22px 22px;
            position: relative;
        }

        .nova-plano .corner-tl, .nova-plano .corner-tr,
        .nova-plano .corner-bl, .nova-plano .corner-br {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            border: 1.5px solid var(--accent);
            opacity: 0.55;
        }

        .nova-plano .corner-tl { top: 8px; left: 8px; border-right: none; border-bottom: none; }
        .nova-plano .corner-tr { top: 8px; right: 8px; border-left: none; border-bottom: none; }
        .nova-plano .corner-bl { bottom: 8px; left: 8px; border-right: none; border-top: none; }
        .nova-plano .corner-br { bottom: 8px; right: 8px; border-left: none; border-top: none; }

        .nova-plano .seg-btn { background: transparent; color: var(--ink-dim); transition: all .15s ease; }
        .nova-plano .seg-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 700; }

        .nova-plano input[type="range"] { -webkit-appearance: none; height: 4px; border-radius: 999px; background: var(--line); }
        .nova-plano input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 14px; height: 14px; border-radius: 999px;
            background: var(--accent); cursor: pointer; border: 2px solid var(--paper);
            box-shadow: 0 0 0 1px var(--accent);
        }

        .nova-plano table.corte-vidrios td,
        .nova-plano table.corte-vidrios th { padding: 4px 6px; }

        .nova-plano .control-card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; }

        .nova-plano .plano-svg-text { paint-order: stroke; stroke: var(--paper); stroke-width: 3px; }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    {{-- =====================================================
         LOADING
    ====================================================== --}}

    <div wire:loading class="fixed inset-0 z-50 bg-white/70 backdrop-blur-[2px]">
        <div class="absolute inset-0 flex items-center justify-center">
            <img src="{{ asset('img/tape.gif') }}" alt="" class="rounded-full w-40 h-40">
        </div>
    </div>

    {{-- =====================================================
         ACCIONES
    ====================================================== --}}

    <div class="flex justify-center gap-3 mb-5">
        <button type="button" wire:click="imprimirTodo"
            class="inline-flex items-center gap-2 px-5 py-2 rounded-full border border-[var(--line)] bg-[var(--panel)] text-xs font-bold uppercase tracking-wider text-[var(--ink)] hover:border-[var(--accent)] hover:text-[var(--accent)] hover:shadow-sm transition shadow-sm">
            <i class="fa-solid fa-print"></i>
            Imprimir plano
        </button>
    </div>

    {{-- =====================================================
         TÍTULO
    ====================================================== --}}

    <div class="flex items-center justify-center gap-2 mb-4">
        <p class="font-display text-3xl font-black tracking-tight text-[var(--ink-dim)]">
            {{ $mamparas[$mamparaActiva]['nombre'] ?? '—' }}
        </p>
    </div>

    {{-- =====================================================
         TABS
    ====================================================== --}}

    <div class="flex flex-wrap items-center gap-2 mb-6 pb-3 border-b border-[var(--line)]">
        @foreach ($mamparas as $index => $v)
            <div class="relative group" wire:key="mampara-tab-{{ $index }}">
                <button wire:click="cambiarMampara({{ $index }})"
                    class="px-5 py-2 text-xs font-bold uppercase tracking-wider rounded-md border transition-all font-mono
                    {{ $mamparaActiva == $index
                        ? 'bg-[var(--panel-soft)] border-[var(--accent)] text-[var(--accent)]'
                        : 'bg-transparent border-[var(--line)] text-[var(--ink-dim)] hover:border-[var(--ink-dim)] hover:text-[var(--ink)]' }}">
                    <i class="fa-solid fa-door-closed mr-1"></i>
                    {{ $v['nombre'] }}
                </button>

                <button wire:click.stop="eliminarMampara({{ $index }})"
                    class="absolute -top-1.5 -right-1.5 w-4 h-4 text-[9px] bg-[var(--danger)] text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                    ✕
                </button>
            </div>
        @endforeach

        <button wire:click="agregarMampara"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider font-mono text-[var(--blue-accent)] border border-dashed border-[var(--line)] rounded-md hover:border-[var(--blue-accent)] transition">
            <i class="fa-solid fa-plus-circle mr-1"></i>
            Nuevo
        </button>

        <button wire:click="limpiarMamparas"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider font-mono text-[var(--danger)]/80 hover:text-[var(--danger)] transition">
            <i class="fa-solid fa-trash mr-1"></i>
            Vaciar
        </button>
    </div>

    {{-- =====================================================
         MEDIDAS
    ====================================================== --}}

    <div class="grid gap-4 mb-5 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">

        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                Ancho <span class="text-[var(--blue-accent)]">(cm)</span>
            </label>
            <input type="number" step="0.1" min="0" wire:model.lazy="ancho"
                class="campo-dim w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 text-sm font-mono font-medium text-[var(--ink)] transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/20 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                Alto <span class="text-[var(--blue-accent)]">(cm)</span>
            </label>
            <input type="number" step="0.1" min="0" wire:model.lazy="alto"
                class="campo-dim w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 text-sm font-mono font-medium text-[var(--ink)] transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/20 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                Sobreluz
            </label>
            <label class="campo-dim flex items-center justify-between gap-2 w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 cursor-pointer">
                <span class="text-sm text-[var(--ink)]">¿Con sobreluz?</span>
                <span class="relative inline-block w-9 h-5 shrink-0">
                    <input type="checkbox" wire:model.lazy="conSobreluz" class="sr-only peer">
                    <span class="absolute inset-0 bg-[var(--line)] rounded-full peer-checked:bg-[var(--accent)] transition"></span>
                    <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-[18px] shadow transition-transform"></span>
                </span>
            </label>
        </div>

        @if ($conSobreluz)
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                    Puente <span class="text-[var(--blue-accent)]">(cm)</span>
                </label>
                <input type="number" step="0.1" min="0" wire:model.lazy="puente"
                    class="campo-dim w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 text-sm font-mono font-medium text-[var(--ink)] transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/20 focus:outline-none">
                <p class="text-[10px] text-[var(--ink-dim)] leading-snug">
                    Altura de la banda principal (hojas), medida desde abajo. El sobreluz toma lo que sobra del alto:
                    {{ number_format($alto, 1) }} − {{ number_format($puente, 1) }} = {{ number_format($this->sobreluzAltura, 1) }} cm.
                </p>
            </div>
        @endif

        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                Corredizas
            </label>
            <input type="number" min="0" wire:model.lazy="corredizas"
                class="campo-dim w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 text-sm font-mono font-medium text-[var(--ink)] transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/20 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-[var(--ink-dim)]">
                Fijos
            </label>
            <input type="number" min="0" wire:model.lazy="fijos"
                class="campo-dim w-full rounded-lg border border-[var(--line)] bg-white px-3 py-2.5 text-sm font-mono font-medium text-[var(--ink)] transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/20 focus:outline-none">
        </div>

    </div>

    {{-- =====================================================
         RESUMEN DE MEDIDAS
    ====================================================== --}}

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">

        <div class="rounded-xl border border-[var(--line)] bg-white p-4">
            <div class="font-mono text-[10px] uppercase tracking-wider text-[var(--ink-dim)]">Medida ingresada</div>
            <div class="mt-1 font-mono text-xl font-bold">
                {{ number_format($ancho, 1) }} × {{ number_format($alto, 1) }}
                <span class="text-sm text-slate-500">cm</span>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="font-mono text-[10px] uppercase tracking-wider text-blue-600">Medida fabricación</div>
            <div class="mt-1 font-mono text-xl font-bold text-blue-700">
                {{ number_format($this->anchoFabricacion, 1) }} × {{ number_format($this->altoFabricacion, 1) }}
                <span class="text-sm">cm</span>
            </div>
        </div>

        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
            <div class="font-mono text-[10px] uppercase tracking-wider text-red-600">Descuento inicial</div>
            <div class="mt-1 font-mono text-xl font-bold text-red-700">-0.5 × -0.5 cm</div>
        </div>

    </div>

    {{-- =====================================================
         BOTÓN PLANO (Alpine puro — no depende de datos del servidor)
    ====================================================== --}}

    <div x-data="{ mostrarPlano: true }">

        <div class="flex justify-center mb-8">
            <button type="button" @click="mostrarPlano = !mostrarPlano"
                class="px-6 py-3 rounded-full bg-[var(--blue-accent)] text-white text-xs sm:text-sm font-bold uppercase tracking-wider shadow-sm hover:opacity-90 transition">
                <i class="fa-solid fa-ruler-combined mr-2"></i>
                <span x-text="mostrarPlano ? 'Plano técnico: Sistema Corredizo' : 'Mostrar plano técnico'"></span>
            </button>
        </div>

        {{-- =====================================================
             PLANO
        ====================================================== --}}

        <div x-show="mostrarPlano" x-cloak>

            <header class="border-b border-[var(--line)] bg-[var(--panel)] rounded-t-xl">
                <div class="max-w-[1400px] mx-auto px-6 py-4 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-md border border-[var(--accent)]/50 flex items-center justify-center font-mono text-[var(--accent)] text-sm font-semibold">
                            C
                        </div>
                        <div>
                            <h1 class="font-display text-lg sm:text-xl font-semibold tracking-tight">
                                Plano de Mampara
                                <span class="text-[var(--accent)]">— Sistema Corredizo</span>
                            </h1>
                            <p class="text-xs text-[var(--ink-dim)] font-mono">
                                Corrediza aluminio · elevación frontal · cotas en cm / mm
                            </p>
                        </div>
                    </div>

                    <div class="text-xs font-mono text-[var(--ink-dim)] text-right leading-tight">
                        <div>Fecha {{ $this->plano['fecha'] }}</div>
                        <div>Escala <span class="text-[var(--blue-accent)]">1:{{ $this->plano['escala'] }}</span></div>
                    </div>
                </div>
            </header>

            <main class="max-w-[1400px] mx-auto px-6 py-6 grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6">

                {{-- =================================================
                     CONTROLES
                ================================================== --}}

                <aside class="bg-[var(--panel)] border border-[var(--line)] rounded-xl p-5 space-y-6 h-fit shadow-sm">

                    {{-- COMPOSICIÓN --}}

                    <div>
                        <h2 class="font-display text-sm font-semibold text-[var(--ink-dim)] uppercase tracking-wider mb-3">
                            Composición
                        </h2>

                        <div class="space-y-1.5 text-[var(--ink)] w-full mb-4 font-mono text-xs">
                            <div class="flex items-center justify-between px-2.5 py-1.5 rounded-md border
                                {{ $this->plano['Puente'] > 0 ? 'border-[var(--blue-accent)] bg-[var(--blue-accent)]/5' : 'border-[var(--line)] opacity-50' }}">
                                <span class="font-semibold {{ $this->plano['Puente'] > 0 ? 'text-[var(--blue-accent)]' : 'text-[var(--ink-dim)]' }}">
                                    Sobreluz arriba
                                </span>
                                <span>
                                    {{ $this->plano['Puente'] > 0 ? number_format($this->plano['Puente'], 1) . ' cm' : 'Sin sobreluz' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between px-2.5 py-1.5 rounded-md border border-[var(--accent)] bg-[var(--accent)]/5">
                                <span class="font-semibold text-[var(--accent)]">Fila principal</span>
                                <span class="font-mono">{{ implode(' - ', $this->distribucionHojas) }}</span>
                            </div>
                        </div>

                        <p class="text-[10px] text-[var(--ink-dim)] -mt-2 mb-4">
                            <span class="text-[var(--blue-accent)] font-semibold">F</span> Fijo ·
                            <span class="text-[var(--accent)] font-semibold">C</span> Corredizo
                        </p>

                        <label class="text-sm block mb-1.5 text-[var(--ink-dim)]">Vidrio</label>

                        <select wire:model.lazy="vidrio"
                            class="w-full bg-[var(--panel-soft)] border border-[var(--line)] rounded-lg px-3 py-2 text-sm font-mono mb-4 text-[var(--ink)] cursor-pointer">
                            <option value="simple">Simple 6mm</option>
                            <option value="doble">Templado 6mm</option>
                            <option value="dvh">DVH 6+12+6mm</option>
                        </select>

                        <label class="flex items-center justify-between cursor-pointer mb-3">
                            <span class="text-sm">Mosquitero</span>
                            <span class="relative inline-block w-9 h-5">
                                <input type="checkbox" wire:model.lazy="mosquitero" class="sr-only peer">
                                <span class="absolute inset-0 bg-[var(--line)] rounded-full peer-checked:bg-[var(--accent)] transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-[18px] shadow transition-transform"></span>
                            </span>
                        </label>
                    </div>

                    {{-- VISTA --}}

                    <div>
                        <h2 class="font-display text-sm font-semibold text-[var(--ink-dim)] uppercase tracking-wider mb-3">
                            Vista del plano
                        </h2>

                        <label class="flex items-center justify-between cursor-pointer mb-3">
                            <span class="text-sm">Mostrar cotas generales</span>
                            <span class="relative inline-block w-9 h-5">
                                <input type="checkbox" wire:model.lazy="mostrarCotas" class="sr-only peer">
                                <span class="absolute inset-0 bg-[var(--line)] rounded-full peer-checked:bg-[var(--accent)] transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-[18px] shadow transition-transform"></span>
                            </span>
                        </label>

                        <label class="flex items-center justify-between cursor-pointer mb-3">
                            <span class="text-sm">Mostrar cota por hoja</span>
                            <span class="relative inline-block w-9 h-5">
                                <input type="checkbox" wire:model.lazy="mostrarCotaHoja" class="sr-only peer">
                                <span class="absolute inset-0 bg-[var(--line)] rounded-full peer-checked:bg-[var(--accent)] transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-[18px] shadow transition-transform"></span>
                            </span>
                        </label>

                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-sm">Mostrar etiquetas de partes</span>
                            <span class="relative inline-block w-9 h-5">
                                <input type="checkbox" wire:model.lazy="mostrarEtiquetas" class="sr-only peer">
                                <span class="absolute inset-0 bg-[var(--line)] rounded-full peer-checked:bg-[var(--accent)] transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-[18px] shadow transition-transform"></span>
                            </span>
                        </label>
                    </div>

                    {{-- CORTE VIDRIOS --}}

                    <div class="pt-4 border-t border-[var(--line)]">
                        <h2 class="font-display text-sm font-semibold text-[var(--ink-dim)] uppercase tracking-wider mb-3">
                            Corte de vidrios
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="corte-vidrios w-full text-xs font-mono">
                                <thead>
                                    <tr class="text-[var(--ink-dim)] text-left border-b border-[var(--line)]">
                                        <th>Hoja</th>
                                        <th>Tipo</th>
                                        <th class="text-right">Ancho</th>
                                        <th class="text-right">Alto</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[var(--ink)]">
                                    @foreach ($this->cortesVidrio as $corte)
                                        <tr class="border-b border-[var(--line)]/60" wire:key="corte-{{ $loop->index }}">
                                            <td>{{ $corte['hoja'] }}</td>
                                            <td class="{{ $corte['tipo'] === 'Fijo' ? 'text-[var(--blue-accent)]' : 'text-[var(--accent)]' }} font-semibold">
                                                {{ $corte['tipo'] }}
                                            </td>
                                            <td class="text-right">{{ number_format($corte['ancho'], 1) }} cm</td>
                                            <td class="text-right">{{ number_format($corte['alto'], 1) }} cm</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="text-[10px] text-[var(--ink-dim)] mt-2 leading-snug">
                            <strong>Primero:</strong> ancho − 0.5 cm y alto − 0.5 cm.<br>
                            <strong>Fijo:</strong> ancho hoja − 4 cm · alto de banda − 5.5 cm.<br>
                            <strong>Corredizo:</strong> ancho hoja + 4 cm · alto de banda − 6.5 cm.<br>
                            <strong>Sobreluz:</strong> ancho fabricación − 4 cm · altura de sobreluz − 5.5 cm.
                        </p>
                    </div>

                </aside>

                {{-- =================================================
                     DIBUJO — SVG generado en PHP, reactivo vía Livewire
                ================================================== --}}

                <section class="relative min-h-[600px] bg-[var(--panel)] border border-[var(--line)] rounded-xl grid-bg p-4 overflow-hidden">
                    <span class="corner-tl"></span>
                    <span class="corner-tr"></span>
                    <span class="corner-bl"></span>
                    <span class="corner-br"></span>

                    <div class="w-full min-h-[500px] flex items-center justify-center">

                        @php($p = $this->plano)

                        <svg viewBox="0 0 1100 760" xmlns="http://www.w3.org/2000/svg"
                            width="100%" preserveAspectRatio="xMidYMid meet"
                            style="display:block;width:100%;height:auto;min-height:500px;">

                            <defs>
                                <pattern id="hatchGlass" width="9" height="9" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
                                    <line x1="0" y1="0" x2="0" y2="9" stroke="#2C5F8A" stroke-opacity="0.20" stroke-width="1" />
                                </pattern>

                                <marker id="arrowStart" markerWidth="8" markerHeight="8" refX="2" refY="4" orient="auto">
                                    <path d="M7 0 L1 4 L7 8" fill="none" stroke="#B23A2E" stroke-width="1.2" />
                                </marker>

                                <marker id="arrowEnd" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
                                    <path d="M1 0 L7 4 L1 8" fill="none" stroke="#B23A2E" stroke-width="1.2" />
                                </marker>
                            </defs>

                            <rect x="0" y="0" width="1100" height="760" fill="#FAF9F6" />

                            {{-- MARCO EXTERIOR --}}
                            <rect x="{{ $p['x0'] }}" y="{{ $p['y0'] }}" width="{{ $p['w'] }}" height="{{ $p['h'] }}"
                                fill="none" stroke="#23282D" stroke-width="3" />

                            {{-- MARCO INTERIOR --}}
                            <rect x="{{ $p['innerX'] }}" y="{{ $p['innerY'] }}" width="{{ $p['innerW'] }}" height="{{ $p['innerH'] }}"
                                fill="none" stroke="#23282D" stroke-width="1.5" />

                            {{-- SOBRELUZ --}}
                            @if ($p['haySobreluz'])
                                <rect x="{{ $p['innerX'] }}" y="{{ $p['innerY'] }}" width="{{ $p['innerW'] }}" height="{{ $p['sobreluzH'] }}"
                                    fill="none" stroke="#23282D" stroke-width="1.3" />

                                <rect x="{{ $p['innerX'] + $p['fh'] }}" y="{{ $p['innerY'] + $p['fh'] }}"
                                    width="{{ max(2, $p['innerW'] - $p['fh'] * 2) }}" height="{{ max(2, $p['sobreluzH'] - $p['fh'] * 2) }}"
                                    fill="#EAF2F7" />

                                <rect x="{{ $p['innerX'] + $p['fh'] }}" y="{{ $p['innerY'] + $p['fh'] }}"
                                    width="{{ max(2, $p['innerW'] - $p['fh'] * 2) }}" height="{{ max(2, $p['sobreluzH'] - $p['fh'] * 2) }}"
                                    fill="url(#hatchGlass)" stroke="#2C5F8A" stroke-opacity="0.4" stroke-width="1" />

                                <rect x="{{ $p['innerX'] }}" y="{{ $p['innerY'] + $p['sobreluzH'] }}"
                                    width="{{ $p['innerW'] }}" height="{{ $p['travesanoPx'] }}"
                                    fill="#F1EEE6" stroke="#23282D" stroke-width="1.5" />
                            @endif

                            {{-- HOJAS --}}
                            @foreach ($p['panels'] as $panel)
                                <rect x="{{ $panel['hx'] }}" y="{{ $p['filaY'] }}" width="{{ $panel['hw'] }}" height="{{ max($p['filaH'], 3) }}"
                                    fill="none" stroke="#23282D" stroke-width="1.4" />

                                <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}" width="{{ $panel['gw'] }}" height="{{ $panel['gh'] }}"
                                    fill="#EAF2F7" />

                                <rect x="{{ $panel['gx'] }}" y="{{ $panel['gy'] }}" width="{{ $panel['gw'] }}" height="{{ $panel['gh'] }}"
                                    fill="url(#hatchGlass)" stroke="#2C5F8A" stroke-opacity="0.45" stroke-width="1" />

                                @if ($panel['esCorrediza'])
                                    <rect x="{{ $panel['handleX'] }}" y="{{ $panel['handleY'] }}" width="6" height="30" rx="2" fill="#B23A2E" />

                                    @if ($mosquitero)
                                        <rect x="{{ $panel['hx'] + 4 }}" y="{{ $p['filaY'] + 4 }}"
                                            width="{{ max($panel['hw'] - 8, 2) }}" height="{{ max($p['filaH'] - 8, 2) }}"
                                            fill="none" stroke="#2C5F8A" stroke-width="1" stroke-dasharray="4 3" />
                                    @endif
                                @endif

                                @if ($mostrarCotaHoja)
                                    @php($midX = ($panel['hx'] + $panel['hx'] + $panel['hw']) / 2)
                                    @php($dimY = $p['filaY'] + $p['filaH'] + 28)
                                    <line x1="{{ $panel['hx'] }}" y1="{{ $dimY }}" x2="{{ $panel['hx'] + $panel['hw'] }}" y2="{{ $dimY }}"
                                        stroke="#B23A2E" stroke-width="1" marker-start="url(#arrowStart)" marker-end="url(#arrowEnd)" />
                                    <line x1="{{ $panel['hx'] }}" y1="{{ $dimY - 7 }}" x2="{{ $panel['hx'] }}" y2="{{ $dimY + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                    <line x1="{{ $panel['hx'] + $panel['hw'] }}" y1="{{ $dimY - 7 }}" x2="{{ $panel['hx'] + $panel['hw'] }}" y2="{{ $dimY + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                    <rect x="{{ $midX - 35 }}" y="{{ $dimY - 22 }}" width="70" height="18" fill="#FAF9F6" />
                                    <text x="{{ $midX }}" y="{{ $dimY - 9 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="10" fill="#B23A2E">
                                        {{ $panel['cotaLabel'] }}
                                    </text>
                                @endif
                            @endforeach

                            {{-- RIELES --}}
                            @if ($p['nCorredizas'] > 0)
                                <line x1="{{ $p['x0'] }}" y1="{{ $p['railY1'] }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $p['railY1'] }}"
                                    stroke="#2C5F8A" stroke-width="1.5" stroke-dasharray="6 4" />
                                <line x1="{{ $p['x0'] }}" y1="{{ $p['railY2'] }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $p['railY2'] }}"
                                    stroke="#2C5F8A" stroke-width="1.5" stroke-dasharray="6 4" />
                            @endif

                            {{-- COTAS GENERALES --}}
                            @if ($mostrarCotas)
                                @php($y1 = $p['y0'] - 48)
                                @php($midXTotal = ($p['x0'] + $p['x0'] + $p['w']) / 2)
                                <line x1="{{ $p['x0'] }}" y1="{{ $y1 }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $y1 }}" stroke="#B23A2E" stroke-width="1" marker-start="url(#arrowStart)" marker-end="url(#arrowEnd)" />
                                <line x1="{{ $p['x0'] }}" y1="{{ $y1 - 7 }}" x2="{{ $p['x0'] }}" y2="{{ $y1 + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                <line x1="{{ $p['x0'] + $p['w'] }}" y1="{{ $y1 - 7 }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $y1 + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                <rect x="{{ $midXTotal - 90 }}" y="{{ $y1 - 22 }}" width="180" height="18" fill="#FAF9F6" />
                                <text x="{{ $midXTotal }}" y="{{ $y1 - 9 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="13" fill="#B23A2E">
                                    {{ number_format($p['Wcm'], 1) }} cm ({{ number_format($p['Wcm'] * 10, 0) }} mm)
                                </text>

                                @php($y2 = $p['y0'] - 20)
                                <line x1="{{ $p['x0'] }}" y1="{{ $y2 }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $y2 }}" stroke="#B23A2E" stroke-width="1" marker-start="url(#arrowStart)" marker-end="url(#arrowEnd)" />
                                <line x1="{{ $p['x0'] }}" y1="{{ $y2 - 7 }}" x2="{{ $p['x0'] }}" y2="{{ $y2 + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                <line x1="{{ $p['x0'] + $p['w'] }}" y1="{{ $y2 - 7 }}" x2="{{ $p['x0'] + $p['w'] }}" y2="{{ $y2 + 7 }}" stroke="#B23A2E" stroke-width="1" />
                                <rect x="{{ $midXTotal - 90 }}" y="{{ $y2 - 22 }}" width="180" height="18" fill="#FAF9F6" />
                                <text x="{{ $midXTotal }}" y="{{ $y2 - 9 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="10" fill="#B23A2E">
                                    FAB: {{ number_format($p['anchoFabricacion'], 1) }} cm
                                </text>

                                @php($x1 = $p['x0'] - 48)
                                @php($midYTotal = ($p['y0'] + $p['y0'] + $p['h']) / 2)
                                <line x1="{{ $x1 }}" y1="{{ $p['y0'] }}" x2="{{ $x1 }}" y2="{{ $p['y0'] + $p['h'] }}" stroke="#B23A2E" stroke-width="1" marker-start="url(#arrowStart)" marker-end="url(#arrowEnd)" />
                                <line x1="{{ $x1 - 7 }}" y1="{{ $p['y0'] }}" x2="{{ $x1 + 7 }}" y2="{{ $p['y0'] }}" stroke="#B23A2E" stroke-width="1" />
                                <line x1="{{ $x1 - 7 }}" y1="{{ $p['y0'] + $p['h'] }}" x2="{{ $x1 + 7 }}" y2="{{ $p['y0'] + $p['h'] }}" stroke="#B23A2E" stroke-width="1" />
                                <rect x="{{ $x1 - 48 }}" y="{{ $midYTotal - 9 }}" width="96" height="18" fill="#FAF9F6" />
                                <text x="{{ $x1 }}" y="{{ $midYTotal + 4 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="13" fill="#B23A2E" transform="rotate(-90 {{ $x1 }} {{ $midYTotal }})">
                                    {{ number_format($p['Hcm'], 1) }} cm ({{ number_format($p['Hcm'] * 10, 0) }} mm)
                                </text>

                                @if ($p['haySobreluz'])
                                    @php($xS = $p['x0'] + $p['w'] + 55)
                                    @php($midYS = ($p['innerY'] + $p['innerY'] + $p['sobreluzH']) / 2)
                                    <line x1="{{ $xS }}" y1="{{ $p['innerY'] }}" x2="{{ $xS }}" y2="{{ $p['innerY'] + $p['sobreluzH'] }}" stroke="#B23A2E" stroke-width="1" marker-start="url(#arrowStart)" marker-end="url(#arrowEnd)" />
                                    <line x1="{{ $xS - 7 }}" y1="{{ $p['innerY'] }}" x2="{{ $xS + 7 }}" y2="{{ $p['innerY'] }}" stroke="#B23A2E" stroke-width="1" />
                                    <line x1="{{ $xS - 7 }}" y1="{{ $p['innerY'] + $p['sobreluzH'] }}" x2="{{ $xS + 7 }}" y2="{{ $p['innerY'] + $p['sobreluzH'] }}" stroke="#B23A2E" stroke-width="1" />
                                    <rect x="{{ $xS - 48 }}" y="{{ $midYS - 9 }}" width="96" height="18" fill="#FAF9F6" />
                                    <text x="{{ $xS }}" y="{{ $midYS + 4 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="11" fill="#B23A2E" transform="rotate(-90 {{ $xS }} {{ $midYS }})">
                                        {{ number_format($p['Puente'], 1) }} cm
                                    </text>
                                @endif
                            @endif

                            {{-- ETIQUETAS --}}
                            @if ($mostrarEtiquetas)
                                <circle cx="{{ $p['x0'] + $p['w'] * 0.20 }}" cy="{{ $p['y0'] }}" r="2.5" fill="#2C5F8A" />
                                <line x1="{{ $p['x0'] + $p['w'] * 0.20 }}" y1="{{ $p['y0'] }}" x2="{{ $p['x0'] + $p['w'] * 0.20 - 40 }}" y2="{{ $p['y0'] - 85 }}" stroke="#2C5F8A" stroke-width="1" />
                                <text x="{{ $p['x0'] + $p['w'] * 0.20 - 40 - 6 }}" y="{{ $p['y0'] - 81 }}" text-anchor="end" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Marco superior</text>

                                <circle cx="{{ $p['x0'] }}" cy="{{ $p['y0'] + $p['h'] * 0.5 }}" r="2.5" fill="#2C5F8A" />
                                <line x1="{{ $p['x0'] }}" y1="{{ $p['y0'] + $p['h'] * 0.5 }}" x2="{{ $p['x0'] - 85 }}" y2="{{ $p['y0'] + $p['h'] * 0.5 }}" stroke="#2C5F8A" stroke-width="1" />
                                <text x="{{ $p['x0'] - 85 - 6 }}" y="{{ $p['y0'] + $p['h'] * 0.5 + 4 }}" text-anchor="end" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Jamba lateral</text>

                                @if ($p['haySobreluz'])
                                    <circle cx="{{ $p['innerX'] + $p['innerW'] / 2 }}" cy="{{ $p['innerY'] + $p['sobreluzH'] / 2 }}" r="2.5" fill="#2C5F8A" />
                                    <line x1="{{ $p['innerX'] + $p['innerW'] / 2 }}" y1="{{ $p['innerY'] + $p['sobreluzH'] / 2 }}" x2="{{ $p['x0'] + $p['w'] * 0.58 }}" y2="{{ $p['innerY'] - 35 }}" stroke="#2C5F8A" stroke-width="1" />
                                    <text x="{{ $p['x0'] + $p['w'] * 0.58 + 6 }}" y="{{ $p['innerY'] - 31 }}" text-anchor="start" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Sobreluz fijo</text>
                                @endif

                                @if ($p['nFijos'] > 0)
                                    <circle cx="{{ $p['innerX'] + $p['innerW'] * 0.18 }}" cy="{{ $p['filaY'] + $p['filaH'] / 2 }}" r="2.5" fill="#2C5F8A" />
                                    <line x1="{{ $p['innerX'] + $p['innerW'] * 0.18 }}" y1="{{ $p['filaY'] + $p['filaH'] / 2 }}" x2="{{ $p['x0'] - 80 }}" y2="{{ $p['filaY'] + $p['filaH'] * 0.40 }}" stroke="#2C5F8A" stroke-width="1" />
                                    <text x="{{ $p['x0'] - 80 - 6 }}" y="{{ $p['filaY'] + $p['filaH'] * 0.40 + 4 }}" text-anchor="end" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Paño fijo</text>
                                @endif

                                @if ($p['nCorredizas'] > 0)
                                    <circle cx="{{ $p['innerX'] + $p['innerW'] * 0.82 }}" cy="{{ $p['filaY'] + $p['filaH'] / 2 }}" r="2.5" fill="#2C5F8A" />
                                    <line x1="{{ $p['innerX'] + $p['innerW'] * 0.82 }}" y1="{{ $p['filaY'] + $p['filaH'] / 2 }}" x2="{{ $p['x0'] + $p['w'] + 85 }}" y2="{{ $p['filaY'] + $p['filaH'] * 0.35 }}" stroke="#2C5F8A" stroke-width="1" />
                                    <text x="{{ $p['x0'] + $p['w'] + 85 + 6 }}" y="{{ $p['filaY'] + $p['filaH'] * 0.35 + 4 }}" text-anchor="start" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Hoja corrediza</text>

                                    <circle cx="{{ $p['x0'] + $p['w'] / 2 }}" cy="{{ $p['railY1'] }}" r="2.5" fill="#2C5F8A" />
                                    <line x1="{{ $p['x0'] + $p['w'] / 2 }}" y1="{{ $p['railY1'] }}" x2="{{ $p['x0'] + $p['w'] / 2 }}" y2="{{ $p['y0'] + $p['h'] + 45 }}" stroke="#2C5F8A" stroke-width="1" />
                                    <text x="{{ $p['x0'] + $p['w'] / 2 }}" y="{{ $p['y0'] + $p['h'] + 49 }}" text-anchor="start" font-family="Inter, sans-serif" font-size="12" fill="#2C5F8A" class="plano-svg-text">Riel corredizo</text>
                                @endif
                            @endif

                            {{-- CARTOUCHE --}}
                            @php($tbY = 760 - 155)
                            @php($tbX = 70)
                            @php($tbW = 1100 - 140)
                            <rect x="{{ $tbX }}" y="{{ $tbY }}" width="{{ $tbW }}" height="125" fill="#FAF9F6" stroke="#23282D" stroke-width="1" />

                            @php($col1 = $tbX + 18)
                            @php($col2 = $tbX + $tbW * 0.37)
                            @php($col3 = $tbX + $tbW * 0.68)

                            @foreach ([
                                [$col1, 22, 'SISTEMA', 'Corredizo · Mampara'],
                                [$col1, 52, 'DIMENSIÓN', number_format($p['Wcm'], 1) . ' x ' . number_format($p['Hcm'], 1) . ' cm'],
                                [$col1, 82, 'FABRICACIÓN', number_format($p['anchoFabricacion'], 1) . ' x ' . number_format($p['altoFabricacion'], 1) . ' cm'],
                                [$col1, 112, 'N° HOJAS', $p['numHojas'] . ' hojas'],
                                [$col2, 22, 'VIDRIO', $p['vidrioLabel']],
                                [$col2, 52, 'PERFIL MARCO', '45 mm'],
                                [$col2, 82, 'PERFIL HOJA', '40 mm'],
                                [$col2, 112, 'MOSQUITERO', $mosquitero ? 'Sí' : 'No'],
                                [$col3, 22, 'PLANO', 'SC-' . number_format($p['Wcm'], 0) . 'x' . number_format($p['Hcm'], 0)],
                                [$col3, 52, 'FECHA', $p['fecha']],
                                [$col3, 82, 'ESCALA', '1:' . $p['escala']],
                                [$col3, 112, 'REVISIÓN', 'A'],
                            ] as [$cx, $cyOffset, $label, $value])
                                <text x="{{ $cx }}" y="{{ $tbY + $cyOffset }}" font-family="JetBrains Mono, monospace" font-size="9.5" fill="#6B7280" letter-spacing="0.5">{{ $label }}</text>
                                <text x="{{ $cx }}" y="{{ $tbY + $cyOffset + 15 }}" font-family="Inter, sans-serif" font-size="13" fill="#23282D" font-weight="600">{{ $value }}</text>
                            @endforeach

                        </svg>

                    </div>
                </section>

            </main>

            {{-- =====================================================
                 PERFILES
            ====================================================== --}}

            <div class="max-w-[1400px] mx-auto px-6 pb-6">
                <div class="bg-[var(--panel)] border border-[var(--line)] rounded-xl overflow-hidden shadow-sm">
                    <div class="grid grid-cols-[1fr_auto_auto] gap-4 px-6 py-3 border-b border-[var(--line)] bg-[var(--panel-soft)] font-mono text-[10px] font-bold uppercase tracking-wider text-[var(--ink-dim)]">
                        <span>Detalle del perfil / accesorio</span>
                        <span>Medida</span>
                        <span class="text-right pr-1">Cant.</span>
                    </div>

                    <div>
                        @foreach ($this->perfilesMampara as $perfil)
                            <div class="grid grid-cols-[1fr_auto_auto] items-center gap-4 px-6 py-3.5 border-b border-[var(--line)]/70" wire:key="perfil-{{ $loop->index }}">
                                <div>
                                    <p class="text-sm font-bold text-[var(--blue-accent)]">{{ $perfil['codigo'] }} - {{ $perfil['nombre'] }}</p>
                                    <p class="font-mono text-[10px] text-[var(--ink-dim)]">COD: {{ $perfil['codigo'] }}</p>
                                </div>
                                <span class="font-mono text-sm font-bold px-3 py-1 rounded-md bg-[var(--panel-soft)] text-[var(--ink)] whitespace-nowrap">
                                    {{ number_format($perfil['medida'], 1) }} cm
                                </span>
                                <span class="font-mono text-sm font-bold w-8 h-8 flex items-center justify-center rounded-md border border-[var(--line)] text-[var(--ink)]">
                                    {{ $perfil['cant'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <p class="text-[10px] text-[var(--ink-dim)] mt-2 leading-snug font-mono">
                    Las piezas 7955, 5283 y 8413 utilizan la medida de fabricación:
                    <strong>{{ number_format($this->anchoFabricacion, 1) }} cm</strong>.
                    El descuento inicial de 0.5 cm ya está aplicado.
                </p>
            </div>

        </div>

    </div>

    <p class="max-w-[1400px] mx-auto px-6 pb-8 text-xs text-[var(--ink-dim)] font-mono">
        Plano referencial de fabricación · perfiles y cotas aproximados
        al sistema de mamparas correderas de aluminio "Corredizo" ·
        verificar con proveedor antes de ejecutar la obra.
    </p>

</div>
