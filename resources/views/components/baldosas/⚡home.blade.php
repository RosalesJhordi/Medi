<?php

use Livewire\Component;

new class extends Component
{
    /** Polígonos dibujados, en metros: [ [[x,y],[x,y],...], ... ] */
    public array $poligonos = [];

    public string $modo = 'piso';          // 'piso' | 'cielo'
    public string $tipo = '60x60';
    public float $anchoBaldosa = 0.6;
    public float $largoBaldosa = 0.6;
    public int $espesorPegamento = 5;      // mm
    public float $juntaMm = 3;             // mm
    public float $espesorBaldosaMm = 10;   // mm
    public float $desperdicioManual = 10;  // %
    public float $puertaM = 0.9;           // m descontados del zócalo
    public float $mermaCielo = 12;         // % de baldosas extra por cortes (cielo raso)
    public float $mermaPerfiles = 5;       // % extra en perfiles secundarios y terciarios (cortes)
    public float $alturaSuspension = 0.30; // m entre losa y cielo (para el alambre)

    public array $res = [];

    public function mount(): void
    {
        // Proyecto de ejemplo: forma en L
        $this->poligonos = [[[0.5, 0.5], [5.5, 0.5], [5.5, 3.5], [3.5, 3.5], [3.5, 5], [0.5, 5]]];
        $this->calcular();
    }

    public function updated(string $prop): void
    {
        if ($prop === 'tipo') {
            [$a, $l] = array_map('floatval', explode('x', $this->tipo));
            $this->anchoBaldosa = $a / 100;
            $this->largoBaldosa = $l / 100;
        }
        $this->calcular();
    }

    public function setModo(string $modo): void
    {
        $this->modo = $modo === 'cielo' ? 'cielo' : 'piso';

        if ($this->modo === 'cielo') {
            $this->tipo = '60x60';
            $this->anchoBaldosa = 0.6;
            $this->largoBaldosa = 0.6;
        }
        $this->calcular();
    }

    public function calcular(): void
    {
        if ($this->modo === 'cielo') {
            $this->calcularCielo();
            return;
        }

        $area = $perimetro = 0.0;
        $completas = $cortes = 0;

        foreach ($this->poligonos as $p) {
            if (count($p) < 3) continue;
            $area += $this->area($p);
            $perimetro += $this->perimetro($p);
            [$c, $k] = $this->contar($p);
            $completas += $c;
            $cortes += $k;
        }

        $areaBaldosa = $this->anchoBaldosa * $this->largoBaldosa;
        $total = $completas + $cortes;
        $necesarias = (int) ceil($total * (1 + $this->desperdicioManual / 100));
        $desperdicio = max(0, $total * $areaBaldosa - $area);

        // Pegamento: ~0.8 kg/m² por mm de espesor, +10% de margen
        $pegKg = $area * $this->espesorPegamento * 0.8 * 1.1;

        // Fragua: (L+W)/(L*W) * espesor * junta * 1.6  (medidas en mm)
        $L = $this->anchoBaldosa * 1000;
        $W = $this->largoBaldosa * 1000;
        $fraKg = $area * (($L + $W) / ($L * $W)) * $this->espesorBaldosaMm * $this->juntaMm * 1.6;

        $this->res = [
            'area'        => round($area, 2),
            'completas'   => $completas,
            'cortes'      => $cortes,
            'total'       => $total,
            'necesarias'  => $necesarias,
            'desperdicio' => round($desperdicio, 2),
            'desp_pct'    => $total ? round($desperdicio / ($total * $areaBaldosa) * 100, 1) : 0,
            'pegamento'   => (int) ceil($pegKg / 25),
            'fragua'      => max(1, (int) ceil($fraKg / 5)),
            'crucetas'    => (int) ceil(($total * 4) / 200),
            'zocalos'     => round(max(0, $perimetro - $this->puertaM), 2),
            'perimetro'   => round($perimetro, 2),
        ];
    }

    /**
     * Cielo raso con baldosa 60x60 y perfilería tipo T (módulo 0.61 m).
     *  - Perfiles principales (3.66 m): líneas paralelas al lado largo, cada 1.22 m (la 1ª a 0.61 m de la pared).
     *  - Colgantes: cada 1.20 m sobre cada línea principal (+1 por tramo).
     *  - Secundarios 1.22 m: líneas cada 0.61 m perpendiculares a los principales, de principal a principal
     *    (los tramos cortos junto a las paredes se cortan de piezas enteras y se reaprovechan los sobrantes).
     *  - Terciarios 0.61 m: líneas intermedias (cada 1.22 m), una pieza entre cada par de secundarios.
     *  - Ángulo perimetral 3.05 m: perímetro + 10 %. Fijaciones: cada 0.50 m.
     */
    private function calcularCielo(): void
    {
        $area = $perimetro = $largo = 0.0;
        $principales = $colgantes = 0;
        $layoutLineas = $layoutColgantes = [];
        $tramos122 = $tramos061 = $lados = [];
        $mejor = -1.0;
        $anchoSup = $anchoInf = 0.0;

        foreach ($this->poligonos as $p) {
            if (count($p) < 3) continue;

            $aPol = $this->area($p);
            $area += $aPol;
            $perimetro += $this->perimetro($p);
            for ($i = 0, $nv = count($p); $i < $nv; $i++) {
                $lados[] = hypot($p[($i + 1) % $nv][0] - $p[$i][0], $p[($i + 1) % $nv][1] - $p[$i][1]);
            }

            $xs = array_column($p, 0);
            $ys = array_column($p, 1);
            $w = max($xs) - min($xs);
            $h = max($ys) - min($ys);
            $alongX = $w >= $h;                       // los principales corren por el lado largo
            $largo = max($largo, max($w, $h));
            $perpMin = $alongX ? min($ys) : min($xs);
            $perpExt = $alongX ? $h : $w;

            // Ancho en cada extremo del lado largo (figura principal)
            if ($aPol >= $mejor) {
                $mejor = $aPol;
                $lo = $alongX ? min($xs) : min($ys);
                $hi = $alongX ? max($xs) : max($ys);
                $anchoSup = $this->anchoEn($p, $lo + 0.005, !$alongX);
                $anchoInf = $this->anchoEn($p, $hi - 0.005, !$alongX);
            }

            for ($i = 0; ($c = $perpMin + ($i + 0.5) * 1.22) < $perpMin + $perpExt - 1e-6; $i++) {
                foreach ($this->segmentos($p, $c, $alongX) as [$a, $b]) {
                    $len = $b - $a;
                    if ($len < 0.05) continue;

                    $principales += (int) ceil(round($len / 3.66, 6));
                    $n = (int) ceil(round($len / 1.2, 6)) + 1;
                    $colgantes += $n;

                    $layoutLineas[] = $alongX
                        ? [round($a, 3), round($c, 3), round($b, 3), round($c, 3)]
                        : [round($c, 3), round($a, 3), round($c, 3), round($b, 3)];

                    $ins = min(0.2, $len / 4);
                    for ($k = 0; $k < $n; $k++) {
                        $t = $n > 1 ? $a + $ins + $k * ($len - 2 * $ins) / ($n - 1) : ($a + $b) / 2;
                        $layoutColgantes[] = $alongX ? [round($t, 3), round($c, 3)] : [round($c, 3), round($t, 3)];
                    }
                }
            }

            // Posiciones (eje perpendicular) de los perfiles principales
            $posPrincipales = [];
            for ($i = 0; ($c = $perpMin + 0.61 + $i * 1.22) < $perpMin + $perpExt - 1e-6; $i++) $posPrincipales[] = $c;
            $vMin = $alongX ? min($xs) : min($ys);
            $vMax = $alongX ? max($xs) : max($ys);

            // Secundarios 1.22 m: una línea cada 0.61 m a lo largo; cada línea se corta en los principales
            for ($i = 1; ($v = $vMin + $i * 0.61) < $vMax - 1e-6; $i++) {
                foreach ($this->segmentos($p, $v, !$alongX) as [$a, $b]) {
                    $cortes = [$a];
                    foreach ($posPrincipales as $m) {
                        if ($m > $a + 1e-6 && $m < $b - 1e-6) $cortes[] = $m;
                    }
                    $cortes[] = $b;
                    for ($j = 0; $j + 1 < count($cortes); $j++) {
                        $l = $cortes[$j + 1] - $cortes[$j];
                        if ($l > 0.02) $tramos122[] = $l;
                    }
                }
            }

            // Terciarios 0.61 m: líneas intermedias (cada 1.22 m), una pieza entre cada par de secundarios
            for ($i = 1; ($u = $perpMin + $i * 1.22) < $perpMin + $perpExt - 1e-6; $i++) {
                foreach ($this->segmentos($p, $u, $alongX) as [$a, $b]) {
                    $cortes = [$a];
                    for ($j = 1; ($v = $vMin + $j * 0.61) < $b - 1e-6; $j++) {
                        if ($v > $a + 1e-6) $cortes[] = $v;
                    }
                    $cortes[] = $b;
                    for ($j = 0; $j + 1 < count($cortes); $j++) {
                        $l = $cortes[$j + 1] - $cortes[$j];
                        if ($l > 0.02) $tramos061[] = $l;
                    }
                }
            }
        }

        $margen = 1 + $this->mermaPerfiles / 100;
        $baldosas = (int) ceil($area / 0.36 * (1 + $this->mermaCielo / 100));
        $secundarios = (int) ceil($this->empaquetar($tramos122, 1.22) * $margen);
        $terciarios = (int) ceil($this->empaquetar($tramos061, 0.61) * $margen);
        $angulos = $this->empaquetar($lados, 3.05);   // cada lado se corta; los sobrantes se reaprovechan
        $fijMin = (int) ceil($perimetro / 0.5) + 1;
        $fijMax = (int) ceil($perimetro / 0.5 * 1.10);
        // Alambre galvanizado N° 12 (BWG 12, Ø 2.77 mm): ≈ 0.0473 kg/m (acero 7850 kg/m³)
        $alambreM = $colgantes * ($this->alturaSuspension + 0.30);   // altura + 0.30 m de amarre por punto
        $alambreKg = $alambreM * 0.0473;

        $this->res = [
            'area'      => round($area, 2),
            'largo'     => round($largo, 2),
            'ancho_sup' => round($anchoSup, 2),
            'ancho_inf' => round($anchoInf, 2),
            'ancho'     => $largo > 0 ? round($area / $largo, 2) : 0,
            'perimetro' => round($perimetro, 2),
            'baldosas'  => $baldosas,
            'lista'     => [
                ['⬜', 'Baldosas 60 x 60 cm',               $baldosas . ' und'],
                ['▬',  'Perfil T principal 3.66 m',         $principales . ' und'],
                ['▬',  'Perfil T secundario 1.22 m',        $secundarios . ' und'],
                ['▬',  'Perfil T terciario 0.61 m',         $terciarios . ' und'],
                ['◣',  'Ángulo perimetral 3.05 m',          $angulos . ' und'],
                ['🪝', 'Puntos de suspensión',              $colgantes . ' und'],
                ['⚓', 'Anclajes para suspensión',          $colgantes . ' und'],
                ['🔩', 'Fijaciones para ángulo perimetral', ($fijMin === $fijMax ? $fijMin : "{$fijMin}–{$fijMax}") . ' und'],
                ['➰', 'Alambre galvanizado N° 12 (≈ ' . number_format($alambreM, 1) . ' m)',          number_format($alambreKg, 2) . ' kg'],
            ],
            'layout'    => ['mains' => $layoutLineas, 'colgantes' => $layoutColgantes],
        ];
    }

    /** Croquis del ambiente con cotas (para el listado de cielo raso). */
    public function croquisSvg(): string
    {
        $polys = array_values(array_filter($this->poligonos, fn ($p) => count($p) >= 3));
        if (!$polys) return '';

        $all = array_merge(...$polys);
        $minX = min(array_column($all, 0)); $maxX = max(array_column($all, 0));
        $minY = min(array_column($all, 1)); $maxY = max(array_column($all, 1));
        $w = max($maxX - $minX, 0.01);
        $h = max($maxY - $minY, 0.01);

        $W = 300; $H = 280; $pad = 46;
        $s = min(($W - 2 * $pad) / $w, ($H - 2 * $pad) / $h);
        $ox = ($W - $w * $s) / 2 - $minX * $s;
        $oy = ($H - $h * $s) / 2 - $minY * $s;

        $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="w-full max-h-72">';
        foreach ($polys as $p) {
            $pts = implode(' ', array_map(fn ($q) => round($q[0] * $s + $ox, 1) . ',' . round($q[1] * $s + $oy, 1), $p));
            $svg .= '<polygon points="' . $pts . '" fill="#ffffff" stroke="#15803d" stroke-width="3" stroke-linejoin="round"/>';

            $n = count($p);
            $cx = array_sum(array_column($p, 0)) / $n * $s + $ox;
            $cy = array_sum(array_column($p, 1)) / $n * $s + $oy;

            for ($i = 0; $i < $n; $i++) {
                $a = $p[$i]; $b = $p[($i + 1) % $n];
                $len = hypot($b[0] - $a[0], $b[1] - $a[1]);
                if ($len < 0.05) continue;

                $mx = ($a[0] + $b[0]) / 2 * $s + $ox;
                $my = ($a[1] + $b[1]) / 2 * $s + $oy;
                $nx = -($b[1] - $a[1]); $ny = $b[0] - $a[0];
                $nl = hypot($nx, $ny) ?: 1; $nx /= $nl; $ny /= $nl;
                if ($nx * ($mx - $cx) + $ny * ($my - $cy) < 0) { $nx = -$nx; $ny = -$ny; }

                $off = 14;
                $anchor = $nx > 0.5 ? 'start' : ($nx < -0.5 ? 'end' : 'middle');
                $svg .= '<text x="' . round($mx + $nx * $off, 1) . '" y="' . round($my + $ny * $off, 1) . '" text-anchor="' . $anchor
                    . '" dominant-baseline="central" font-size="12" font-weight="700" fill="#166534">' . number_format($len, 2) . ' m</text>';
            }
        }
        return $svg . '</svg>';
    }

    /**
     * Piezas de longitud $cap necesarias para cubrir los tramos dados.
     * Los tramos se cortan de piezas enteras y los sobrantes se reaprovechan (first-fit decreasing).
     */
    private function empaquetar(array $tramos, float $cap): int
    {
        $piezas = 0;
        $restos = [];

        foreach ($tramos as $l) {
            $enteras = (int) floor(round($l / $cap, 6));
            $piezas += $enteras;
            $r = $l - $enteras * $cap;
            if ($r > 0.02) $restos[] = $r;
        }

        rsort($restos);
        $libres = []; // espacio libre en cada pieza ya abierta
        foreach ($restos as $r) {
            $ok = false;
            foreach ($libres as $i => $libre) {
                if ($r <= $libre + 1e-6) { $libres[$i] -= $r; $ok = true; break; }
            }
            if (!$ok) { $piezas++; $libres[] = $cap - $r; }
        }
        return $piezas;
    }

    /** Longitud total de la línea c que queda dentro del polígono. */
    private function anchoEn(array $p, float $c, bool $alongX): float
    {
        $t = 0.0;
        foreach ($this->segmentos($p, $c, $alongX) as [$a, $b]) $t += $b - $a;
        return $t;
    }

    /** Tramos [a,b] de la línea c que quedan dentro del polígono (a lo largo del eje X o Y). */
    private function segmentos(array $p, float $c, bool $alongX): array
    {
        $vs = [];
        $n = count($p);
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $p[$i];
            [$x2, $y2] = $p[($i + 1) % $n];
            [$u1, $v1, $u2, $v2] = $alongX ? [$y1, $x1, $y2, $x2] : [$x1, $y1, $x2, $y2];

            if (($u1 <= $c && $c < $u2) || ($u2 <= $c && $c < $u1)) {
                $vs[] = $v1 + ($c - $u1) * ($v2 - $v1) / ($u2 - $u1);
            }
        }
        sort($vs);

        $segs = [];
        for ($i = 0; $i + 1 < count($vs); $i += 2) $segs[] = [$vs[$i], $vs[$i + 1]];
        return $segs;
    }

    /** Fórmula del área de Gauss (shoelace). */
    private function area(array $p): float
    {
        $s = 0;
        $n = count($p);
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $p[$i];
            [$x2, $y2] = $p[($i + 1) % $n];
            $s += $x1 * $y2 - $x2 * $y1;
        }
        return abs($s) / 2;
    }

    private function perimetro(array $p): float
    {
        $s = 0;
        $n = count($p);
        for ($i = 0; $i < $n; $i++) {
            $s += hypot($p[($i + 1) % $n][0] - $p[$i][0], $p[($i + 1) % $n][1] - $p[$i][1]);
        }
        return $s;
    }

    /** Ray casting: ¿el punto está dentro del polígono? */
    private function dentro(float $x, float $y, array $p): bool
    {
        $in = false;
        $n = count($p);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $p[$i];
            [$xj, $yj] = $p[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi)) {
                $in = !$in;
            }
        }
        return $in;
    }

    /** Recorre la grilla de baldosas (anclada a la esquina mín. del polígono, igual que el dibujo). */
    private function contar(array $p): array
    {
        $xs = array_column($p, 0);
        $ys = array_column($p, 1);
        $minX = min($xs); $maxX = max($xs);
        $minY = min($ys); $maxY = max($ys);
        $w = max($this->anchoBaldosa, 0.05);
        $l = max($this->largoBaldosa, 0.05);
        $m = 6;
        $completas = $cortes = 0;

        for ($x = $minX; $x < $maxX - 1e-6; $x += $w) {
            for ($y = $minY; $y < $maxY - 1e-6; $y += $l) {
                $hit = 0;
                for ($i = 0; $i < $m; $i++) {
                    for ($j = 0; $j < $m; $j++) {
                        if ($this->dentro($x + ($i + .5) * $w / $m, $y + ($j + .5) * $l / $m, $p)) $hit++;
                    }
                }
                if ($hit === $m * $m) $completas++;
                elseif ($hit > 0) $cortes++;
            }
        }
        return [$completas, $cortes];
    }
};
?>

@assets
<script>
window.baldosaEditor = (wire) => ({
    herramienta: 'select',
    color: '#15803d',
    grosor: 2,
    grid: true,
    escala: 100,                       // 1 m = 100 unidades SVG
    polys: JSON.parse(JSON.stringify(wire.poligonos ?? [])),
    borrador: [],
    cursor: null,
    sel: null,
    accion: null,
    vb: { x: -50, y: -50, w: 1100, h: 660 },
    undo: [],
    redo: [],
    ancho: 5,
    largo: 4,

    init() { this.$nextTick(() => this.ajustar()); },

    /* ---------- utilidades ---------- */
    r2: (v) => Math.round(v * 100) / 100,
    clone() { return JSON.parse(JSON.stringify(this.polys)); },
    get k() { // unidades SVG por píxel de pantalla (tamaños constantes al hacer zoom)
        const s = this.$refs.svg;
        return Math.max(this.vb.w / (s.clientWidth || 1000), this.vb.h / (s.clientHeight || 600));
    },
    svgPt(e) {
        const s = this.$refs.svg, p = s.createSVGPoint();
        p.x = e.clientX; p.y = e.clientY;
        const q = p.matrixTransform(s.getScreenCTM().inverse());
        return { x: q.x, y: q.y };
    },
    m(e) { // coordenada en metros con snap (Alt = precisión de 1 cm)
        const { x, y } = this.svgPt(e), step = e.altKey ? 0.01 : 0.1;
        const sn = (v) => this.r2(Math.round(v / this.escala / step) * step);
        return [sn(x), sn(y)];
    },
    puntos(p) { return p.map(q => `${q[0] * this.escala},${q[1] * this.escala}`).join(' '); },

    /* ---------- historial / sincronización ---------- */
    sync() { wire.$set('poligonos', this.clone(), true); },
    guardar() { this.undo.push(this.clone()); this.redo = []; },
    commit(poly) { this.guardar(); this.polys.push(poly); this.sel = this.polys.length - 1; this.sync(); },
    deshacer() { if (!this.undo.length) return; this.redo.push(this.clone()); this.polys = this.undo.pop(); this.sel = null; this.sync(); },
    rehacer() { if (!this.redo.length) return; this.undo.push(this.clone()); this.polys = this.redo.pop(); this.sel = null; this.sync(); },
    limpiar() { this.guardar(); this.polys = []; this.borrador = []; this.sel = null; this.sync(); },
    eliminar() {
        if (this.sel === null) return;
        this.guardar(); this.polys.splice(this.sel, 1); this.sel = null; this.sync();
    },
    dibujarRect() {
        const w = +this.ancho, l = +this.largo;
        if (!(w > 0 && l > 0)) return;
        this.commit([[.5, .5], [this.r2(.5 + w), .5], [this.r2(.5 + w), this.r2(.5 + l)], [.5, this.r2(.5 + l)]]);
        this.$nextTick(() => this.ajustar());
    },

    /* ---------- vista: zoom / pan / ajustar ---------- */
    ajustar() {
        const pts = this.polys.flat();
        if (!pts.length) { this.vb = { x: -50, y: -50, w: 1100, h: 660 }; return; }
        const e = this.escala, mg = 90;
        let x0 = Math.min(...pts.map(p => p[0])) * e - mg, x1 = Math.max(...pts.map(p => p[0])) * e + mg;
        let y0 = Math.min(...pts.map(p => p[1])) * e - mg, y1 = Math.max(...pts.map(p => p[1])) * e + mg;
        const s = this.$refs.svg, ar = (s.clientWidth || 1000) / (s.clientHeight || 600);
        let w = Math.max(x1 - x0, 300), h = Math.max(y1 - y0, 200);
        if (w / h < ar) { const nw = h * ar; x0 -= (nw - w) / 2; w = nw; } else { const nh = w / ar; y0 -= (nh - h) / 2; h = nh; }
        this.vb = { x: x0, y: y0, w, h };
    },
    zoom(e, factor = null) {
        const f = factor ?? (e.deltaY > 0 ? 1.12 : 1 / 1.12);
        const nw = Math.min(Math.max(this.vb.w * f, 200), 8000), r = nw / this.vb.w;
        const c = e && e.clientX !== undefined ? this.svgPt(e) : { x: this.vb.x + this.vb.w / 2, y: this.vb.y + this.vb.h / 2 };
        this.vb = { x: c.x - (c.x - this.vb.x) * r, y: c.y - (c.y - this.vb.y) * r, w: nw, h: this.vb.h * r };
    },

    /* ---------- interacción ---------- */
    down(e) {
        const t = e.target, p = this.m(e);

        // Clic en la cota de un lado → editar longitud
        if (this.herramienta === 'select' && t.dataset.label) { this.editarLado(t.dataset.label); return; }

        this.$refs.svg.setPointerCapture(e.pointerId);

        // Pan con botón central/derecho
        if (e.button === 1 || e.button === 2) { this.accion = { t: 'pan', sx: e.clientX, sy: e.clientY, vb: { ...this.vb } }; return; }

        if (this.herramienta === 'rect') { this.borrador = [p, p]; return; }

        if (this.herramienta === 'poligono') {
            const b = this.borrador;
            if (b.length >= 3 && Math.hypot(p[0] - b[0][0], p[1] - b[0][1]) < 0.25) { this.cerrar(false); return; }
            const last = b[b.length - 1];
            if (!last || last[0] !== p[0] || last[1] !== p[1]) b.push(p);
            return;
        }

        // Herramienta seleccionar
        if (t.dataset.v) {
            const [i, j] = t.dataset.v.split(':').map(Number);
            this.sel = i; this.guardar(); this.accion = { t: 'v', i, j, moved: false }; return;
        }
        if (t.dataset.poly !== undefined) {
            const i = +t.dataset.poly;
            this.sel = i; this.guardar();
            this.accion = { t: 'poly', i, start: p, orig: JSON.parse(JSON.stringify(this.polys[i])), moved: false }; return;
        }
        this.sel = null;
        this.accion = { t: 'pan', sx: e.clientX, sy: e.clientY, vb: { ...this.vb } };
    },
    move(e) {
        const a = this.accion;
        if (a && a.t === 'pan') {
            const s = this.$refs.svg, k0 = Math.max(a.vb.w / s.clientWidth, a.vb.h / s.clientHeight);
            this.vb = { ...a.vb, x: a.vb.x - (e.clientX - a.sx) * k0, y: a.vb.y - (e.clientY - a.sy) * k0 };
            return;
        }
        const p = this.m(e);
        this.cursor = p;
        if (this.herramienta === 'rect' && this.borrador.length) this.borrador = [this.borrador[0], p];
        if (a && a.t === 'v') { this.polys[a.i][a.j] = p; a.moved = true; }
        if (a && a.t === 'poly') {
            const dx = p[0] - a.start[0], dy = p[1] - a.start[1];
            this.polys[a.i] = a.orig.map(q => [this.r2(q[0] + dx), this.r2(q[1] + dy)]);
            a.moved = true;
        }
    },
    up(e) {
        const a = this.accion;
        if (this.herramienta === 'rect' && this.borrador.length === 2) {
            const [p, q] = this.borrador;
            if (Math.abs(q[0] - p[0]) >= .1 && Math.abs(q[1] - p[1]) >= .1) this.commit([[p[0], p[1]], [q[0], p[1]], [q[0], q[1]], [p[0], q[1]]]);
            this.borrador = [];
        }
        if (a && (a.t === 'v' || a.t === 'poly')) {
            if (a.moved) this.sync(); else this.undo.pop();
        }
        this.accion = null;
        try { this.$refs.svg.releasePointerCapture(e.pointerId); } catch (_) {}
    },
    cerrar(dbl = true) {
        if (this.herramienta !== 'poligono') return;
        if (dbl) this.borrador.pop(); // el doble clic duplica el último punto
        if (this.borrador.length >= 3) this.commit(this.borrador.map(q => [...q]));
        this.borrador = [];
    },
    tecla(e) {
        if (['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName)) return;
        if (e.key === 'Escape') { this.borrador = []; this.accion = null; this.sel = null; }
        if ((e.key === 'Delete' || e.key === 'Backspace') && this.sel !== null) { e.preventDefault(); this.eliminar(); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? this.rehacer() : this.deshacer(); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); this.rehacer(); }
    },

    /** Cambiar la longitud de un lado haciendo clic en su cota. En lados horizontales/verticales mueve la pared completa. */
    editarLado(key) {
        const [i, j] = key.split(':').map(Number), p = this.polys[i], jb = (j + 1) % p.length;
        const a = p[j], b = p[jb], act = Math.hypot(b[0] - a[0], b[1] - a[1]);
        const L = parseFloat((prompt('Longitud del lado (m):', act.toFixed(2)) ?? '').replace(',', '.'));
        if (!(L > 0) || act === 0) return;
        this.guardar();
        const ux = (b[0] - a[0]) / act, uy = (b[1] - a[1]) / act, eps = 1e-6;
        const nb = [this.r2(a[0] + ux * L), this.r2(a[1] + uy * L)];
        if (Math.abs(uy) < eps) { const dx = nb[0] - b[0], ox = b[0]; p.forEach(q => { if (Math.abs(q[0] - ox) < eps) q[0] = this.r2(q[0] + dx); }); }
        else if (Math.abs(ux) < eps) { const dy = nb[1] - b[1], oy = b[1]; p.forEach(q => { if (Math.abs(q[1] - oy) < eps) q[1] = this.r2(q[1] + dy); }); }
        else p[jb] = nb;
        this.sel = i; this.sync();
    },

    /* ---------- render del SVG (string → x-html; evita <template> dentro de SVG) ---------- */
    txt(x, y, s, size, extra = '') {
        return `<text x="${x}" y="${y}" font-size="${size}" fill="#14532d" text-anchor="middle" dominant-baseline="middle" font-weight="700"
            stroke="#ffffff" stroke-width="${size * .35}" paint-order="stroke" stroke-linejoin="round" ${extra}>${s}</text>`;
    },
    render() {
        const e = this.escala, k = this.k, fs = 13 * k, out = [];
        const tw = wire.anchoBaldosa * e, th = wire.largoBaldosa * e;
        const sel = this.herramienta === 'select';

        this.polys.forEach((p, i) => {
            if (p.length < 3) return;
            const bx = Math.min(...p.map(q => q[0])) * e, by = Math.min(...p.map(q => q[1])) * e;
            const on = this.sel === i;
            out.push(`<pattern id="t${i}" x="${bx}" y="${by}" width="${tw}" height="${th}" patternUnits="userSpaceOnUse">
                <rect width="${tw}" height="${th}" fill="#ffffff" stroke="#16a34a" stroke-opacity=".6" stroke-width="${1.2 * k}"/></pattern>`);
            out.push(`<polygon data-poly="${i}" points="${this.puntos(p)}" fill="url(#t${i})" stroke="${on ? '#f59e0b' : this.color}"
                stroke-width="${(this.grosor + 1) * 1.5 * k}" stroke-linejoin="round" style="cursor:${sel ? 'move' : 'crosshair'}"/>`);

            // cotas
            const cx = p.reduce((s, q) => s + q[0], 0) / p.length * e, cy = p.reduce((s, q) => s + q[1], 0) / p.length * e;
            p.forEach((a, j) => {
                const b = p[(j + 1) % p.length], len = Math.hypot(b[0] - a[0], b[1] - a[1]);
                if (len < .05) return;
                const mx = (a[0] + b[0]) / 2 * e, my = (a[1] + b[1]) / 2 * e;
                let nx = -(b[1] - a[1]), ny = b[0] - a[0]; const nl = Math.hypot(nx, ny) || 1; nx /= nl; ny /= nl;
                if (nx * (mx - cx) + ny * (my - cy) < 0) { nx = -nx; ny = -ny; }
                out.push(this.txt(mx + nx * 18 * k, my + ny * 18 * k, len.toFixed(2) + ' m', fs, `data-label="${i}:${j}" style="cursor:${sel ? 'pointer' : 'default'}"`));
            });

            // vértices
            if (sel) p.forEach((q, j) => out.push(`<circle data-v="${i}:${j}" cx="${q[0] * e}" cy="${q[1] * e}" r="${6 * k}" fill="#fff"
                stroke="${on ? '#f59e0b' : this.color}" stroke-width="${2.5 * k}" style="cursor:grab"/>`));
        });

        // cielo raso: perfiles principales (naranja) y puntos de suspensión (rojo)
        const LY = wire.res && wire.res.layout;
        if (wire.modo === 'cielo' && LY) {
            (LY.mains || []).forEach(s => out.push(`<line x1="${s[0] * e}" y1="${s[1] * e}" x2="${s[2] * e}" y2="${s[3] * e}"
                stroke="#ea580c" stroke-width="${3 * k}" stroke-linecap="round" pointer-events="none"/>`));
            (LY.colgantes || []).forEach(q => out.push(`<circle cx="${q[0] * e}" cy="${q[1] * e}" r="${3.5 * k}" fill="#dc2626"
                stroke="#fff" stroke-width="${k}" pointer-events="none"/>`));
        }

        // borrador de polígono
        if (this.herramienta === 'poligono' && this.borrador.length) {
            const b = this.borrador, pts = this.cursor ? [...b, this.cursor] : b;
            out.push(`<polyline points="${this.puntos(pts)}" fill="${this.color}22" stroke="${this.color}" stroke-width="${2 * k}" stroke-dasharray="${6 * k} ${4 * k}" pointer-events="none"/>`);
            b.forEach(q => out.push(`<circle cx="${q[0] * e}" cy="${q[1] * e}" r="${4 * k}" fill="${this.color}" pointer-events="none"/>`));
            if (b.length >= 3) out.push(`<circle cx="${b[0][0] * e}" cy="${b[0][1] * e}" r="${10 * k}" fill="none" stroke="#22c55e" stroke-width="${2 * k}" pointer-events="none"/>`);
            if (this.cursor) {
                const l = b[b.length - 1], c = this.cursor;
                out.push(this.txt((l[0] + c[0]) / 2 * e, (l[1] + c[1]) / 2 * e - 14 * k, Math.hypot(c[0] - l[0], c[1] - l[1]).toFixed(2) + ' m', fs, 'pointer-events="none"'));
            }
        }

        // borrador de rectángulo
        if (this.herramienta === 'rect' && this.borrador.length === 2) {
            const [p, q] = this.borrador, w = Math.abs(q[0] - p[0]), h = Math.abs(q[1] - p[1]);
            out.push(`<rect x="${Math.min(p[0], q[0]) * e}" y="${Math.min(p[1], q[1]) * e}" width="${w * e}" height="${h * e}" fill="${this.color}33"
                stroke="${this.color}" stroke-width="${2 * k}" stroke-dasharray="${6 * k} ${4 * k}" pointer-events="none"/>`);
            out.push(this.txt((p[0] + q[0]) / 2 * e, (p[1] + q[1]) / 2 * e, `${w.toFixed(2)} × ${h.toFixed(2)} m`, fs * 1.1, 'pointer-events="none"'));
        }
        return out.join('');
    },
});
</script>
@endassets

<div x-data="baldosaEditor($wire)" @keydown.window="tecla($event)"
     class="min-h-screen bg-slate-50 text-slate-800 flex">

    {{-- Sidebar --}}
    <aside class="hidden lg:flex w-52 shrink-0 flex-col gap-1 bg-white border-r border-slate-200 p-4 print:hidden">
        <div class="mb-6 text-center">
            <div class="text-3xl">🔷</div>
            <div class="text-xl font-bold text-emerald-900">BaldosaPro</div>
            <div class="text-xs text-slate-500">Calculadora de Baldosas</div>
        </div>
        @foreach (['🏠 Nuevo Proyecto' => true, '📁 Mis Proyectos' => false, '📋 Plantillas' => false, '⚙️ Configuración' => false, '❓ Ayuda' => false] as $item => $active)
            <a href="#" class="rounded-lg px-3 py-2.5 text-sm {{ $active ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $item }}</a>
        @endforeach
        <div class="mt-auto text-xs text-slate-400">© 2026 BaldosaPro<br>Hecho con ❤️ en Perú</div>
    </aside>

    <main class="flex-1 p-4 lg:p-6 space-y-4 min-w-0">
        <header class="print:hidden">
            <h1 class="text-2xl font-bold text-emerald-900">Calculadora de Baldosas</h1>
            <p class="text-sm text-slate-500">Dibuja tu área, ingresa las medidas y obtén el cálculo exacto de material.</p>
        </header>

        {{-- Modo de cálculo --}}
        <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm print:hidden">
            <button type="button" wire:click="setModo('piso')"
                class="rounded-lg px-5 py-2 text-sm transition {{ $modo === 'piso' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">🧱 Piso / Pared</button>
            <button type="button" wire:click="setModo('cielo')"
                class="rounded-lg px-5 py-2 text-sm transition {{ $modo === 'cielo' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">⬜ Cielo raso</button>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm print:hidden">
            @foreach (['select' => '↖ Seleccionar', 'rect' => '▭ Rectángulo', 'poligono' => '⬠ Polígono'] as $key => $label)
                <button type="button" @click="herramienta = '{{ $key }}'; borrador = []"
                    :class="herramienta === '{{ $key }}' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    class="rounded-lg px-4 py-2 text-sm transition">{{ $label }}</button>
            @endforeach
            <span class="mx-1 h-6 w-px bg-slate-200"></span>
            <button type="button" @click="deshacer()" :disabled="!undo.length" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-30">↶ Deshacer</button>
            <button type="button" @click="rehacer()" :disabled="!redo.length" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-30">↷ Rehacer</button>
            <button type="button" @click="eliminar()" :disabled="sel === null" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-30">✂ Eliminar figura</button>
            <button type="button" @click="limpiar()" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">🗑 Limpiar</button>
            <span class="mx-1 h-6 w-px bg-slate-200"></span>
            <button type="button" @click="zoom({}, 1/1.25)" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">＋</button>
            <button type="button" @click="zoom({}, 1.25)" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">－</button>
            <button type="button" @click="ajustar()" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">⤢ Ajustar</button>
        </div>

        <div class="grid gap-4 xl:grid-cols-[220px_1fr_340px]">
            {{-- Propiedades --}}
            <div class="space-y-4 print:hidden">
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <h2 class="font-semibold text-emerald-900">Propiedades</h2>
                    <label class="flex items-center justify-between text-sm">Color
                        <input type="color" x-model="color" class="h-8 w-12 rounded bg-transparent">
                    </label>
                    <label class="flex items-center justify-between text-sm">Grosor
                        <select x-model.number="grosor" class="rounded-md border border-slate-300 bg-white px-2 py-1">
                            <option value="1">1 px</option><option value="2">2 px</option><option value="4">4 px</option>
                        </select>
                    </label>
                    <label class="flex items-center justify-between text-sm">Cuadrícula
                        <input type="checkbox" x-model="grid" class="size-5 accent-emerald-600">
                    </label>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <h2 class="font-semibold text-emerald-900">Medidas Rápidas</h2>
                    <label class="flex items-center justify-between text-sm">Ancho (m)
                        <input type="number" step="0.1" min="0.1" x-model="ancho" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                    </label>
                    <label class="flex items-center justify-between text-sm">Largo (m)
                        <input type="number" step="0.1" min="0.1" x-model="largo" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                    </label>
                    <button type="button" @click="dibujarRect()" class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-500">▭ Dibujar Rectángulo</button>
                </section>
            </div>

            {{-- Lienzo (wire:ignore: Livewire no debe re-renderizar el SVG) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-2 shadow-sm" wire:ignore>
                <svg x-ref="svg" class="h-[560px] w-full touch-none select-none rounded-lg bg-slate-50"
                     x-bind:view-box.camel="`${vb.x} ${vb.y} ${vb.w} ${vb.h}`"
                     :class="herramienta === 'select' ? 'cursor-default' : 'cursor-crosshair'"
                     @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up($event)" @pointercancel="up($event)"
                     @dblclick="cerrar(true)" @wheel.prevent="zoom($event)" @contextmenu.prevent>
                    <defs>
                        <pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse">
                            <path d="M100 0H0V100" fill="none" stroke="#d3e2d8" stroke-width="1" vector-effect="non-scaling-stroke"/>
                        </pattern>
                    </defs>
                    <rect x="-6000" y="-6000" width="12000" height="12000" fill="url(#grid)" x-show="grid" pointer-events="none"/>
                    <g x-html="render()"></g>
                </svg>
                <p class="px-3 pt-2 pb-1 text-xs text-slate-500 print:hidden">
                    <b>Rectángulo:</b> arrastra · <b>Polígono:</b> clic por vértice, cierra con doble clic o clic en el primer punto ·
                    <b>Seleccionar:</b> arrastra figuras o vértices, clic en una cota para cambiar su medida · Rueda = zoom · Arrastra el fondo (o clic derecho) = mover vista ·
                    Supr = borrar · Alt = precisión 1 cm · Esc = cancelar
                </p>
            </section>

            {{-- Columna derecha --}}
            <aside class="space-y-4">
                @if ($modo === 'cielo')
                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4 print:hidden">
                        <h2 class="font-semibold text-emerald-900">Resumen · Cielo raso</h2>
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="rounded-xl bg-emerald-600 p-4 text-white">
                                <div class="text-2xl font-bold">{{ number_format($res['area'], 2) }} m²</div>
                                <div class="text-sm text-emerald-100">Área</div>
                            </div>
                            <div class="rounded-xl bg-emerald-800 p-4 text-white">
                                <div class="text-2xl font-bold">{{ $res['baldosas'] }}</div>
                                <div class="text-sm text-emerald-100">Baldosas</div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">
                            En el plano: <span class="font-semibold text-orange-600">líneas naranjas</span> = perfiles principales,
                            <span class="font-semibold text-red-600">puntos rojos</span> = suspensión.
                            El listado completo está debajo.
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-700 py-2.5 text-sm font-medium text-white hover:bg-emerald-600">📄 Generar PDF</button>
                            <button type="button" onclick="window.print()" class="rounded-lg bg-slate-700 py-2.5 text-sm font-medium text-white hover:bg-slate-600">🖨 Imprimir</button>
                        </div>
                    </section>
                @else
                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                        <h2 class="flex items-center gap-2 font-semibold text-emerald-900"><span class="text-emerald-600">✔</span> Resultados del Cálculo</h2>

                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="rounded-xl bg-emerald-600 p-4 text-white">
                                <div class="text-3xl font-bold">{{ number_format($res['area'], 2) }} <span class="text-lg">m²</span></div>
                                <div class="text-sm text-emerald-100">Área Total</div>
                            </div>
                            <div class="rounded-xl bg-emerald-800 p-4 text-white">
                                <div class="text-3xl font-bold">{{ $res['necesarias'] }}</div>
                                <div class="text-sm text-emerald-100">Baldosas Necesarias</div>
                            </div>
                        </div>

                        <dl class="space-y-2 text-sm">
                            <h3 class="font-semibold text-emerald-900">Desglose de Baldosas</h3>
                            <div class="flex justify-between"><dt>Baldosas completas:</dt><dd class="font-semibold">{{ $res['completas'] }} unid.</dd></div>
                            <div class="flex justify-between"><dt>Baldosas con cortes:</dt><dd class="font-semibold">{{ $res['cortes'] }} unid.</dd></div>
                            <div class="flex justify-between"><dt>Desperdicio real:</dt><dd class="font-semibold">{{ number_format($res['desperdicio'], 2) }} m² ({{ $res['desp_pct'] }}%)</dd></div>
                        </dl>

                        <dl class="space-y-2 text-sm">
                            <h3 class="font-semibold text-emerald-900">Materiales Recomendados</h3>
                            <div class="flex justify-between"><dt>Pegamento (25 kg):</dt><dd class="font-semibold">{{ $res['pegamento'] }} bolsa(s)</dd></div>
                            <div class="flex justify-between"><dt>Fragua (5 kg):</dt><dd class="font-semibold">{{ $res['fragua'] }} bolsa(s)</dd></div>
                            <div class="flex justify-between"><dt>Crucetas (200 u.):</dt><dd class="font-semibold">{{ $res['crucetas'] }} bolsa(s)</dd></div>
                            <div class="flex justify-between"><dt>Zócalos (opcional):</dt><dd class="font-semibold">{{ number_format($res['zocalos'], 2) }} m</dd></div>
                        </dl>

                        <div class="grid grid-cols-2 gap-3 print:hidden">
                            <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-700 py-2.5 text-sm font-medium text-white hover:bg-emerald-600">📄 Generar PDF</button>
                            <button type="button" onclick="window.print()" class="rounded-lg bg-slate-700 py-2.5 text-sm font-medium text-white hover:bg-slate-600">🖨 Imprimir</button>
                        </div>
                    </section>
                @endif

                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 text-sm print:hidden">
                    <h2 class="font-semibold text-emerald-900">Opciones Avanzadas</h2>

                    @if ($modo === 'cielo')
                        <label class="flex items-center justify-between">Merma de baldosas (%)
                            <input type="number" min="0" max="40" wire:model.live.debounce.400ms="mermaCielo" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                        </label>
                        <label class="flex items-center justify-between">Merma de perfiles (%)
                            <input type="number" min="0" max="30" wire:model.live.debounce.400ms="mermaPerfiles" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                        </label>
                        <label class="flex items-center justify-between">Altura de suspensión (m)
                            <input type="number" step="0.05" min="0.1" wire:model.live.debounce.400ms="alturaSuspension" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                        </label>
                    @else
                        <label class="flex items-center justify-between">Tipo de baldosa
                            <select wire:model.live="tipo" class="rounded-md border border-slate-300 bg-white px-2 py-1.5">
                                @foreach (['30x30', '40x40', '45x45', '60x60', '60x120', '80x80'] as $t)
                                    <option value="{{ $t }}">{{ str_replace('x', ' x ', $t) }} cm</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center justify-between">Desperdicio manual (%)
                            <input type="number" min="0" max="50" wire:model.live.debounce.400ms="desperdicioManual" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                        </label>
                        <label class="flex items-center justify-between">Espesor de pegamento
                            <select wire:model.live="espesorPegamento" class="rounded-md border border-slate-300 bg-white px-2 py-1.5">
                                @foreach ([3, 5, 8, 10] as $mm)<option value="{{ $mm }}">{{ $mm }} mm</option>@endforeach
                            </select>
                        </label>
                        <label class="flex items-center justify-between">Junta (mm)
                            <input type="number" step="0.5" min="1" wire:model.live.debounce.400ms="juntaMm" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-right">
                        </label>
                    @endif
                </section>
            </aside>
        </div>

        {{-- Listado de materiales · Cielo raso (documento completo) --}}
        @if ($modo === 'cielo')
            <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm space-y-5 print:border-0 print:shadow-none">
                <div class="flex items-center gap-4">
                    <div class="text-4xl">🟩</div>
                    <div>
                        <h2 class="text-3xl font-extrabold tracking-tight text-emerald-900">LISTADO DE MATERIALES</h2>
                        <span class="mt-1 inline-block rounded-full bg-emerald-700 px-4 py-1 text-lg font-semibold text-white">Cielo raso con baldosa</span>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="mb-3 inline-block rounded-lg bg-emerald-100 px-4 py-1.5 text-lg font-bold text-emerald-900">Medidas del ambiente</h3>
                        <ul class="space-y-2 text-lg">
                            @foreach ([
                                'Largo' => number_format($res['largo'], 2) . ' m',
                                'Ancho superior' => number_format($res['ancho_sup'], 2) . ' m',
                                'Ancho inferior' => number_format($res['ancho_inf'], 2) . ' m',
                                'Ancho promedio' => number_format($res['ancho'], 2) . ' m',
                                'Área aproximada' => number_format($res['area'], 2) . ' m²',
                                'Perímetro' => number_format($res['perimetro'], 2) . ' ml',
                            ] as $etiqueta => $valor)
                                <li class="flex items-center gap-3">
                                    <span class="size-2.5 rounded-sm bg-emerald-700"></span>
                                    <span>{{ $etiqueta }}: <b class="text-emerald-800">{{ $valor }}</b></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-emerald-50/60 p-2">
                        {!! $this->croquisSvg() !!}
                    </div>
                </div>

                <p class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm italic text-emerald-900">
                    <span class="not-italic">ℹ️</span> Cálculo referencial para baldosas de 60 x 60 cm y perfilería tipo T.
                </p>

                <div class="overflow-hidden rounded-xl border border-emerald-700">
                    <table class="w-full text-lg">
                        <thead>
                            <tr class="bg-emerald-800 text-white">
                                <th class="px-4 py-3 text-center font-bold">Material</th>
                                <th class="px-4 py-3 text-center font-bold">Cantidad estimada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($res['lista'] as $fila)
                                <tr class="border-t border-emerald-200">
                                    <td class="px-4 py-3"><span class="mr-3 inline-block w-8 text-center text-slate-500">{{ $fila[0] }}</span>{{ $fila[1] }}</td>
                                    <td class="bg-emerald-50 px-4 py-3 text-center font-extrabold text-emerald-900">{{ $fila[2] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm italic text-emerald-900">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-700 not-italic text-white">✔</span>
                    Incluye margen básico por cortes y desperdicio. Verificar en obra según nivelación, estructura existente y altura de suspensión.
                </p>
            </section>
        @endif
    </main>
</div>
