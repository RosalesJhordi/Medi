<?php

use Livewire\Component;

new class extends Component {
    // Sin tipado para evitar errores cuando el input queda vacío
    public $ancho = 101;   // cuerda (medida recta de borde a borde)
    public $flecha = 13;
    public $alto = 143;

    // Vista 3D
    public $giro = -35;
    public $inclinacion = 22;

    /*
    |--------------------------------------------------------------------------
    | CÁLCULO DEL ARCO
    |--------------------------------------------------------------------------
    */

    public function getInfoProperty(): array
    {
        $c = (float) ($this->ancho ?: 0);
        $f = (float) ($this->flecha ?: 0);
        $h = (float) ($this->alto ?: 0);

        $base = [
            'ok' => false,
            'c' => $c,
            'f' => $f,
            'h' => $h,
            'r' => 0,
            'rad' => 0,
            'angulo' => 0,
            'desarrollo' => 0,
            'areaM2' => 0,
            'semicirculo' => false,
        ];

        if ($c <= 0 || $f <= 0) {
            return $base;
        }

        $r = ($c * $c) / (8 * $f) + $f / 2;

        $ratio = min(1, $c / (2 * $r));
        $rad = 2 * asin($ratio);

        // Si la flecha supera la mitad de la cuerda el arco pasa de 180°
        $mayor = $f > $c / 2;

        if ($mayor) {
            $rad = 2 * M_PI - $rad;
        }

        $desarrollo = $r * $rad;

        return array_merge($base, [
            'ok' => true,
            'r' => $r,
            'rad' => $rad,
            'angulo' => rad2deg($rad),
            'desarrollo' => $desarrollo,
            'areaM2' => ($desarrollo * $h) / 10000,
            'semicirculo' => $mayor,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRESETS DE VISTA 3D
    |--------------------------------------------------------------------------
    */

    public function vista(string $v): void
    {
        [$this->giro, $this->inclinacion] = match ($v) {
            'frontal' => [0, 0],
            'planta' => [0, 90],
            'lateral' => [-90, 0],
            default => [-35, 22],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | GEOMETRÍA DEL PLANO 2D (planta + elevación)
    |--------------------------------------------------------------------------
    */

    public function getPlano2dProperty(): array
    {
        $i = $this->info;

        if (!$i['ok']) {
            return ['ok' => false];
        }

        $r = $i['r'];
        $th = $i['rad'];
        $c = $i['c'];
        $f = $i['f'];
        $h = $i['h'];

        $zOff = $r * cos($th / 2);
        $n = 60;

        $pts = [];

        for ($k = 0; $k <= $n; $k++) {
            $a = -$th / 2 + $th * $k / $n;
            $pts[] = [$r * sin($a), $r * cos($a) - $zOff, $a];
        }

        $xs = array_column($pts, 0);
        $minx = min($xs);
        $maxx = max($xs);
        $bw = max(0.01, $maxx - $minx);
        $bh = max(0.01, $f);
        $cxm = ($minx + $maxx) / 2;

        // ---------- PLANTA ----------
        $s = min(430 / $bw, 185 / $bh);
        $ox = 285;
        $yTop = 105 + (185 - $bh * $s) / 2;
        $yz0 = $yTop + $bh * $s;

        $P = fn ($x, $z) => [$ox + ($x - $cxm) * $s, $yz0 - $z * $s];

        $poly = [];

        foreach ($pts as $p) {
            [$px, $py] = $P($p[0], $p[1]);
            $poly[] = round($px, 2) . ',' . round($py, 2);
        }

        [$ex1, $ey1] = $P(-$c / 2, 0);
        [$ex2, $ey2] = $P($c / 2, 0);
        [$mx, $my] = $P(0, $f);
        [$cx0, $cy0] = $P(0, 0);

        $aR = -$th / 4;
        $aT = $th / 4;
        [$rx, $ry] = $P($r * sin($aR), $r * cos($aR) - $zOff);
        [$tx, $ty] = $P($r * sin($aT), $r * cos($aT) - $zOff);

        // ---------- ELEVACIÓN ----------
        $s2 = min(220 / $bw, 190 / max(0.01, $h));
        $rw = $bw * $s2;
        $rh = max(0.01, $h) * $s2;
        $cxE = 710;
        $xE = $cxE - $rw / 2;
        $yE = 75 + (190 - $rh) / 2;

        $guias = [];

        for ($k = -3; $k <= 3; $k++) {
            $a = $k * ($th / 2) / 4;
            $guias[] = $cxE + ($r * sin($a) - $cxm) * $s2;
        }

        return [
            'ok' => true,
            'poly' => implode(' ', $poly),
            'ex1' => $ex1, 'ey1' => $ey1,
            'ex2' => $ex2, 'ey2' => $ey2,
            'mx' => $mx, 'my' => $my,
            'cx0' => $cx0, 'cy0' => $cy0,
            'yz0' => $yz0,
            'rx' => $rx, 'ry' => $ry,
            'tx' => $tx, 'ty' => $ty,
            'xE' => $xE, 'yE' => $yE, 'rw' => $rw, 'rh' => $rh,
            'guias' => $guias,
            'bw' => $bw,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | VISTA 3D (proyección ortográfica calculada en PHP)
    |--------------------------------------------------------------------------
    */

    public function getVista3dProperty(): array
    {
        $i = $this->info;

        if (!$i['ok'] || $i['h'] <= 0) {
            return ['ok' => false];
        }

        $r = $i['r'];
        $th = $i['rad'];
        $h = $i['h'];
        $c = $i['c'];
        $f = $i['f'];

        $t = max(0.6, $c * 0.012);   // espesor ilustrativo
        $rt = max(0.01, $r - $t);
        $n = 44;

        $yaw = deg2rad((float) $this->giro);
        $pit = deg2rad(max(0, min(90, (float) $this->inclinacion)));

        $cyw = cos($yaw);
        $syw = sin($yaw);
        $cp = cos($pit);
        $sp = sin($pit);

        $zOff = $r * cos($th / 2);
        $zc = $f / 2;
        $yc = $h / 2;

        // [x, y, z] del mundo → [X, Y, profundidad]
        $proj = function ($x, $y, $z) use ($cyw, $syw, $cp, $sp, $zc, $yc) {
            $z -= $zc;
            $y -= $yc;

            $x1 = $x * $cyw + $z * $syw;
            $z1 = -$x * $syw + $z * $cyw;

            return [$x1, -($y * $cp - $z1 * $sp), $y * $sp + $z1 * $cp];
        };

        $o = [];
        $in = [];

        for ($k = 0; $k <= $n; $k++) {
            $a = -$th / 2 + $th * $k / $n;
            $o[] = [$r * sin($a), $r * cos($a) - $zOff, $a];
            $in[] = [$rt * sin($a), $rt * cos($a) - $zOff, $a];
        }

        $quads = [];

        for ($k = 0; $k < $n; $k++) {
            $am = ($o[$k][2] + $o[$k + 1][2]) / 2;
            $nzv = abs(-sin($am) * $syw + cos($am) * $cyw);

            // cara exterior
            $quads[] = ['k' => 'o', 'l' => $nzv, 'p' => [
                [$o[$k][0], 0, $o[$k][1]], [$o[$k + 1][0], 0, $o[$k + 1][1]],
                [$o[$k + 1][0], $h, $o[$k + 1][1]], [$o[$k][0], $h, $o[$k][1]],
            ]];

            // cara interior
            $quads[] = ['k' => 'i', 'l' => $nzv, 'p' => [
                [$in[$k][0], 0, $in[$k][1]], [$in[$k + 1][0], 0, $in[$k + 1][1]],
                [$in[$k + 1][0], $h, $in[$k + 1][1]], [$in[$k][0], $h, $in[$k][1]],
            ]];

            // canto superior e inferior
            foreach ([0, $h] as $yy) {
                $quads[] = ['k' => 'e', 'l' => 0, 'p' => [
                    [$o[$k][0], $yy, $o[$k][1]], [$o[$k + 1][0], $yy, $o[$k + 1][1]],
                    [$in[$k + 1][0], $yy, $in[$k + 1][1]], [$in[$k][0], $yy, $in[$k][1]],
                ]];
            }
        }

        // cantos laterales
        foreach ([0, $n] as $k) {
            $quads[] = ['k' => 'e', 'l' => 0, 'p' => [
                [$o[$k][0], 0, $o[$k][1]], [$o[$k][0], $h, $o[$k][1]],
                [$in[$k][0], $h, $in[$k][1]], [$in[$k][0], 0, $in[$k][1]],
            ]];
        }

        // proyección y caja contenedora
        $minx = $miny = INF;
        $maxx = $maxy = -INF;

        foreach ($quads as &$q) {
            $dsum = 0;
            $q['pp'] = [];

            foreach ($q['p'] as $v) {
                $pp = $proj($v[0], $v[1], $v[2]);
                $q['pp'][] = $pp;
                $dsum += $pp[2];
                $minx = min($minx, $pp[0]);
                $maxx = max($maxx, $pp[0]);
                $miny = min($miny, $pp[1]);
                $maxy = max($maxy, $pp[1]);
            }

            $q['d'] = $dsum / 4;
        }
        unset($q);

        $bw = max(1e-6, $maxx - $minx);
        $bh = max(1e-6, $maxy - $miny);
        $s = min(780 / $bw, 320 / $bh);
        $ox = 450 - (($minx + $maxx) / 2) * $s;
        $oy = 195 - (($miny + $maxy) / 2) * $s;

        $fin = fn ($pp) => [$ox + $pp[0] * $s, $oy + $pp[1] * $s];

        usort($quads, fn ($a, $b) => $a['d'] <=> $b['d']);

        $polys = [];

        foreach ($quads as $q) {
            $str = [];

            foreach ($q['pp'] as $pp) {
                [$X, $Y] = $fin($pp);
                $str[] = round($X, 1) . ',' . round($Y, 1);
            }

            if ($q['k'] === 'o') {
                $fill = 'hsl(205,70%,' . round(52 + 30 * $q['l']) . '%)';
                $op = 0.55;
            } elseif ($q['k'] === 'i') {
                $fill = 'hsl(210,60%,' . round(44 + 26 * $q['l']) . '%)';
                $op = 0.42;
            } else {
                $fill = '#4F8DB8';
                $op = 0.92;
            }

            $polys[] = ['pts' => implode(' ', $str), 'fill' => $fill, 'op' => $op, 'edge' => $q['k'] === 'e'];
        }

        // piso (cuadrícula)
        $gx = max($c, $h) * 0.75;
        $gz = $gx * 0.6;
        $z0 = $f / 2 - $gz;
        $z1 = $f / 2 + $gz;

        $grid = [];

        $line = function ($xa, $za, $xb, $zb) use ($proj, $fin) {
            [$X1, $Y1] = $fin($proj($xa, 0, $za));
            [$X2, $Y2] = $fin($proj($xb, 0, $zb));
            return [round($X1, 1), round($Y1, 1), round($X2, 1), round($Y2, 1)];
        };

        for ($j = 0; $j <= 8; $j++) {
            $z = $z0 + ($z1 - $z0) * $j / 8;
            $grid[] = $line(-$gx, $z, $gx, $z);
        }

        for ($j = 0; $j <= 10; $j++) {
            $x = -$gx + 2 * $gx * $j / 10;
            $grid[] = $line($x, $z0, $x, $z1);
        }

        $floor = [];

        foreach ([[-$gx, $z0], [$gx, $z0], [$gx, $z1], [-$gx, $z1]] as [$gxx, $gzz]) {
            [$X, $Y] = $fin($proj($gxx, 0, $gzz));
            $floor[] = round($X, 1) . ',' . round($Y, 1);
        }

        // etiquetas de medidas
        [$ax, $ay] = $fin($proj(0, 0, 0));
        [$hx, $hy] = $fin($proj($o[0][0], $h / 2, $o[0][1]));

        return [
            'ok' => true,
            'polys' => $polys,
            'grid' => $grid,
            'floor' => implode(' ', $floor),
            'anchoX' => $ax, 'anchoY' => $ay,
            'altoX' => $hx, 'altoY' => $hy,
        ];
    }
};
?>

@php
    $i = $this->info;
    $pl = $this->plano2d;
    $v3 = $this->vista3d;

    $ink = '#1E293B';
    $dim = '#C2410C';
    $acc = '#4F46E5';

    $inputClase = 'w-full px-4 py-3 pr-12 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none';

    // COTA HORIZONTAL
    $dimH = function ($xa, $xb, $y, $yRef, $txt, $fs = 12) use ($dim) {
        $m = ($xa + $xb) / 2;
        $w = mb_strlen($txt) * $fs * 0.6 + 18;
        $e = $yRef > $y ? -6 : 6;
        return '<g fill="none" stroke="' . $dim . '" stroke-width="1">'
            . '<line x1="' . $xa . '" y1="' . $yRef . '" x2="' . $xa . '" y2="' . ($y + $e) . '" stroke-opacity=".45"/>'
            . '<line x1="' . $xb . '" y1="' . $yRef . '" x2="' . $xb . '" y2="' . ($y + $e) . '" stroke-opacity=".45"/>'
            . '<line x1="' . $xa . '" y1="' . $y . '" x2="' . $xb . '" y2="' . $y . '"/>'
            . '<line x1="' . ($xa - 5) . '" y1="' . ($y + 5) . '" x2="' . ($xa + 5) . '" y2="' . ($y - 5) . '" stroke-width="1.8"/>'
            . '<line x1="' . ($xb - 5) . '" y1="' . ($y + 5) . '" x2="' . ($xb + 5) . '" y2="' . ($y - 5) . '" stroke-width="1.8"/>'
            . '</g>'
            . '<rect x="' . ($m - $w / 2) . '" y="' . ($y - 10) . '" width="' . $w . '" height="20" rx="10" fill="#fff" stroke="' . $dim . '" stroke-width=".8"/>'
            . '<text x="' . $m . '" y="' . ($y + 4) . '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' . $fs . '" font-weight="500" fill="' . $dim . '">' . e($txt) . '</text>';
    };

    // COTA VERTICAL
    $dimV = function ($x, $ya, $yb, $xRef, $txt, $fs = 12) use ($dim) {
        $m = ($ya + $yb) / 2;
        $w = mb_strlen($txt) * $fs * 0.6 + 18;
        $e = $xRef > $x ? -6 : 6;
        return '<g fill="none" stroke="' . $dim . '" stroke-width="1">'
            . '<line x1="' . $xRef . '" y1="' . $ya . '" x2="' . ($x + $e) . '" y2="' . $ya . '" stroke-opacity=".45"/>'
            . '<line x1="' . $xRef . '" y1="' . $yb . '" x2="' . ($x + $e) . '" y2="' . $yb . '" stroke-opacity=".45"/>'
            . '<line x1="' . $x . '" y1="' . $ya . '" x2="' . $x . '" y2="' . $yb . '"/>'
            . '<line x1="' . ($x - 5) . '" y1="' . ($ya + 5) . '" x2="' . ($x + 5) . '" y2="' . ($ya - 5) . '" stroke-width="1.8"/>'
            . '<line x1="' . ($x - 5) . '" y1="' . ($yb + 5) . '" x2="' . ($x + 5) . '" y2="' . ($yb - 5) . '" stroke-width="1.8"/>'
            . '</g>'
            . '<g transform="rotate(-90 ' . $x . ' ' . $m . ')">'
            . '<rect x="' . ($x - $w / 2) . '" y="' . ($m - 10) . '" width="' . $w . '" height="20" rx="10" fill="#fff" stroke="' . $dim . '" stroke-width=".8"/>'
            . '<text x="' . $x . '" y="' . ($m + 4) . '" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="' . $fs . '" font-weight="500" fill="' . $dim . '">' . e($txt) . '</text>'
            . '</g>';
    };

    // ETIQUETA CON LÍNEA GUÍA: 'l' = pastilla a la izquierda del punto final, 'r' = a la derecha
    $call = function ($px, $py, $txt, $x, $y, $mode = 'l') use ($acc) {
        $w = mb_strlen($txt) * 6.4 + 18;
        $rx = $mode === 'l' ? $x - $w : $x;
        return '<line x1="' . $px . '" y1="' . $py . '" x2="' . $x . '" y2="' . $y . '" stroke="' . $acc . '" stroke-width="1" stroke-opacity=".7"/>'
            . '<circle cx="' . $px . '" cy="' . $py . '" r="3" fill="#fff" stroke="' . $acc . '" stroke-width="1.5"/>'
            . '<rect x="' . $rx . '" y="' . ($y - 11) . '" width="' . $w . '" height="22" rx="11" fill="' . $acc . '"/>'
            . '<text x="' . ($rx + $w / 2) . '" y="' . ($y + 4) . '" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#fff">' . e($txt) . '</text>';
    };

    $n1 = fn ($v) => number_format($v, 1, '.', '');
    $n2 = fn ($v) => number_format($v, 2, '.', '');
@endphp

<div class="max-w-6xl mx-auto p-6 md:p-10 bg-white text-slate-800 rounded-3xl shadow-xl border border-slate-200">

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 mb-8 border-b border-slate-200 gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-indigo-500">Calculadora</p>
            <h1 class="text-3xl font-extrabold text-slate-900">
                Plano 2D y 3D · Vidrio Curvo
            </h1>
        </div>

        <div class="flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-100 rounded-xl">
            <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
            <span class="text-xs font-semibold text-slate-600">cálculo en tiempo real</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- ===================== COLUMNA IZQUIERDA ===================== -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- PARÁMETROS -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h3 class="font-bold text-slate-700 mb-5">Parámetros</h3>

                <div class="space-y-4">
                    @foreach ([
                        ['ancho', 'Ancho (cuerda)'],
                        ['flecha', 'Flecha'],
                        ['alto', 'Alto'],
                    ] as [$campo, $etiqueta])
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">{{ $etiqueta }}</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" inputmode="decimal" wire:model.lazy="{{ $campo }}" class="{{ $inputClase }}">
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">cm</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($i['semicirculo'])
                    <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-semibold leading-snug text-amber-700 ring-1 ring-amber-200">
                        La flecha es mayor que la mitad del ancho: el arco pasa de 180°.
                    </p>
                @endif
            </div>

            <!-- RESULTADOS -->
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 shadow-sm">
                <h3 class="font-bold text-blue-700 mb-5">Resultados</h3>

                <div class="space-y-3 text-sm">
                    @foreach ([
                        ['Desarrollo (arco)', $n2($i['desarrollo']) . ' cm'],
                        ['Radio', $n2($i['r']) . ' cm'],
                        ['Ángulo', $n2($i['angulo']) . '°'],
                    ] as [$k, $val])
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">{{ $k }}</span>
                            <span class="font-bold text-slate-900">{{ $val }}</span>
                        </div>
                    @endforeach

                    <div class="border-t border-blue-200 pt-3 mt-3 flex items-center justify-between">
                        <span class="text-slate-600">Alto</span>
                        <span class="font-bold text-slate-900">{{ $n2($i['h']) }} cm</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Vidrio plano a cortar</span>
                        <span class="font-bold text-slate-900">{{ $n1($i['desarrollo']) }} × {{ $n1($i['h']) }} cm</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Área</span>
                        <span class="font-bold text-slate-900">{{ $n2($i['areaM2']) }} m²</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== COLUMNA DERECHA ===================== -->
        <div class="lg:col-span-8 flex flex-col gap-6">

            <!-- PLANO 2D -->
            <section x-data="{ ver: true }" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800 font-mono text-sm font-bold text-white">2D</span>
                        <div>
                            <h2 class="text-base font-extrabold tracking-tight text-slate-800">Plano técnico</h2>
                            <p class="text-[11px] text-slate-400">Planta y elevación · cotas en cm</p>
                        </div>
                    </div>

                    <button type="button" @click="ver = !ver"
                        class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white transition hover:bg-slate-700 active:scale-95">
                        <i class="fa-solid" :class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                        <span x-text="ver ? 'Ocultar' : 'Mostrar'"></span>
                    </button>
                </div>

                <div x-show="ver" x-cloak class="bg-[#F8FAFC] p-3 sm:p-4">
                    @if ($pl['ok'])
                        <svg viewBox="0 0 900 420" xmlns="http://www.w3.org/2000/svg" width="100%" preserveAspectRatio="xMidYMid meet"
                            style="display:block;width:100%;height:auto;">

                            <defs>
                                <pattern id="vcGrid" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <path d="M20 0H0V20" fill="none" stroke="#CBD5E1" stroke-opacity=".35" stroke-width=".6" />
                                </pattern>

                                <linearGradient id="vcCurve" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0" stop-color="#8DBEDD" />
                                    <stop offset=".35" stop-color="#E3F2FB" />
                                    <stop offset=".55" stop-color="#CBE6F7" />
                                    <stop offset="1" stop-color="#86B7D8" />
                                </linearGradient>

                                <filter id="vcShadow" x="-10%" y="-10%" width="120%" height="125%">
                                    <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#0F172A" flood-opacity=".18" />
                                </filter>
                            </defs>

                            <rect width="900" height="420" fill="#F8FAFC" />
                            <rect width="900" height="420" fill="url(#vcGrid)" />

                            {{-- paneles --}}
                            <rect x="30" y="24" width="500" height="322" rx="14" fill="#fff" stroke="#E2E8F0" />
                            <rect x="550" y="24" width="320" height="322" rx="14" fill="#fff" stroke="#E2E8F0" />

                            <text x="50" y="50" font-family="JetBrains Mono, monospace" font-size="11" letter-spacing="2" fill="#64748B" font-weight="500">PLANTA</text>
                            <text x="570" y="50" font-family="JetBrains Mono, monospace" font-size="11" letter-spacing="2" fill="#64748B" font-weight="500">ELEVACIÓN</text>

                            {{-- ===== PLANTA ===== --}}
                            <line x1="{{ $pl['ex1'] }}" y1="{{ $pl['ey1'] }}" x2="{{ $pl['ex2'] }}" y2="{{ $pl['ey2'] }}"
                                stroke="#64748B" stroke-width="1" stroke-dasharray="6 4" />

                            <g filter="url(#vcShadow)">
                                <polyline points="{{ $pl['poly'] }}" fill="none" stroke="#2C5F8A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                            </g>
                            <polyline points="{{ $pl['poly'] }}" fill="none" stroke="#BBDDF2" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />

                            {{-- flecha --}}
                            <line x1="{{ $pl['mx'] }}" y1="{{ $pl['my'] }}" x2="{{ $pl['mx'] }}" y2="{{ $pl['yz0'] }}" stroke="{{ $dim }}" stroke-width="1" stroke-dasharray="3 3" />
                            <line x1="{{ $pl['mx'] - 5 }}" y1="{{ $pl['my'] + 5 }}" x2="{{ $pl['mx'] + 5 }}" y2="{{ $pl['my'] - 5 }}" stroke="{{ $dim }}" stroke-width="1.8" />
                            <line x1="{{ $pl['mx'] - 5 }}" y1="{{ $pl['yz0'] + 5 }}" x2="{{ $pl['mx'] + 5 }}" y2="{{ $pl['yz0'] - 5 }}" stroke="{{ $dim }}" stroke-width="1.8" />
                            @php
                                $fTxt = 'F ' . $n1($i['f']) . ' cm';
                                $fW = mb_strlen($fTxt) * 12 * 0.6 + 18;
                                $fY = ($pl['my'] + $pl['yz0']) / 2;
                            @endphp
                            <rect x="{{ $pl['mx'] + 14 }}" y="{{ $fY - 10 }}" width="{{ $fW }}" height="20" rx="10" fill="#fff" stroke="{{ $dim }}" stroke-width=".8" />
                            <text x="{{ $pl['mx'] + 14 + $fW / 2 }}" y="{{ $fY + 4 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="12" font-weight="500" fill="{{ $dim }}">{{ $fTxt }}</text>

                            {{-- cuerda --}}
                            {!! $dimH($pl['ex1'], $pl['ex2'], $pl['yz0'] + 36, $pl['yz0'] + 6, 'ANCHO ' . $n1($i['c']) . ' cm', 12) !!}

                            {{-- radio y ángulo --}}
                            {!! $call($pl['rx'], $pl['ry'], 'R = ' . $n1($i['r']) . ' cm', $pl['rx'] - 34, $pl['ry'] - 30, 'l') !!}
                            {!! $call($pl['tx'], $pl['ty'], 'θ = ' . $n1($i['angulo']) . '°', $pl['tx'] + 34, $pl['ty'] - 30, 'r') !!}

                            {{-- ===== ELEVACIÓN ===== --}}
                            <g filter="url(#vcShadow)">
                                <rect x="{{ $pl['xE'] }}" y="{{ $pl['yE'] }}" width="{{ $pl['rw'] }}" height="{{ $pl['rh'] }}"
                                    fill="url(#vcCurve)" stroke="{{ $ink }}" stroke-width="1.8" />
                            </g>

                            @foreach ($pl['guias'] as $gx)
                                <line x1="{{ $gx }}" y1="{{ $pl['yE'] }}" x2="{{ $gx }}" y2="{{ $pl['yE'] + $pl['rh'] }}"
                                    stroke="#2C5F8A" stroke-opacity=".35" stroke-width="1" stroke-dasharray="4 3" />
                            @endforeach

                            <path d="M{{ $pl['xE'] + $pl['rw'] * .12 }} {{ $pl['yE'] + $pl['rh'] }} L{{ $pl['xE'] + $pl['rw'] * .26 }} {{ $pl['yE'] }} L{{ $pl['xE'] + $pl['rw'] * .32 }} {{ $pl['yE'] }} L{{ $pl['xE'] + $pl['rw'] * .18 }} {{ $pl['yE'] + $pl['rh'] }}Z"
                                fill="#fff" fill-opacity=".45" />

                            {!! $dimH($pl['xE'], $pl['xE'] + $pl['rw'], $pl['yE'] + $pl['rh'] + 34, $pl['yE'] + $pl['rh'] + 6, $n1($pl['bw']) . ' cm', 12) !!}
                            {!! $dimV($pl['xE'] + $pl['rw'] + 38, $pl['yE'], $pl['yE'] + $pl['rh'], $pl['xE'] + $pl['rw'] + 8, $n1($i['h']) . ' cm', 12) !!}

                            {{-- ===== CARTUCHO ===== --}}
                            @php
                                $tbX = 30; $tbY = 356; $tbW = 840; $tbH = 58;
                                $cols = [$tbX + 24, $tbX + 24 + $tbW * 0.25, $tbX + 24 + $tbW * 0.5, $tbX + 24 + $tbW * 0.75];
                                $celdas = [
                                    [0, 0, 'ANCHO (CUERDA)', $n1($i['c']) . ' cm'],
                                    [1, 0, 'FLECHA', $n1($i['f']) . ' cm'],
                                    [2, 0, 'ALTO', $n1($i['h']) . ' cm'],
                                    [3, 0, 'ÁREA', $n2($i['areaM2']) . ' m²'],
                                    [0, 1, 'RADIO', $n2($i['r']) . ' cm'],
                                    [1, 1, 'ÁNGULO', $n2($i['angulo']) . '°'],
                                    [2, 1, 'DESARROLLO (ARCO)', $n2($i['desarrollo']) . ' cm'],
                                    [3, 1, 'VIDRIO PLANO', $n1($i['desarrollo']) . ' × ' . $n1($i['h']) . ' cm'],
                                ];
                            @endphp

                            <rect x="{{ $tbX }}" y="{{ $tbY }}" width="{{ $tbW }}" height="{{ $tbH }}" rx="10" fill="#fff" stroke="#CBD5E1" />
                            <rect x="{{ $tbX }}" y="{{ $tbY }}" width="8" height="{{ $tbH }}" rx="4" fill="{{ $acc }}" />
                            <line x1="{{ $tbX + 20 }}" y1="{{ $tbY + $tbH / 2 }}" x2="{{ $tbX + $tbW - 14 }}" y2="{{ $tbY + $tbH / 2 }}" stroke="#E2E8F0" />

                            @foreach ($celdas as [$cc, $rr, $label, $value])
                                <text x="{{ $cols[$cc] }}" y="{{ $tbY + 12 + $rr * 28 }}" font-family="JetBrains Mono, monospace" font-size="9" fill="#94A3B8" letter-spacing="1">{{ $label }}</text>
                                <text x="{{ $cols[$cc] }}" y="{{ $tbY + 25 + $rr * 28 }}" font-family="Inter, sans-serif" font-size="12.5" font-weight="700" fill="{{ $ink }}">{{ $value }}</text>
                            @endforeach
                        </svg>
                    @else
                        <div class="py-20 text-center text-sm italic text-slate-400">
                            Ingresa un ancho y una flecha mayores a 0 para ver el plano
                        </div>
                    @endif
                </div>

                <div x-show="!ver" x-cloak class="py-16 text-center text-sm italic text-slate-400">Plano oculto</div>
            </section>

            <!-- VISTA 3D -->
            <section x-data="{ ver: true }" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 font-mono text-sm font-bold text-white">3D</span>
                        <div>
                            <h2 class="text-base font-extrabold tracking-tight text-slate-800">Vista 3D de la curvatura</h2>
                            <p class="text-[11px] text-slate-400">Gira y cambia la inclinación para ver el vidrio</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ([['iso', 'Isométrica'], ['frontal', 'Frontal'], ['lateral', 'Lateral'], ['planta', 'Planta']] as [$clave, $texto])
                            <button type="button" wire:click="vista('{{ $clave }}')"
                                class="rounded-full bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200 transition hover:text-indigo-600 hover:ring-indigo-300 active:scale-95">
                                {{ $texto }}
                            </button>
                        @endforeach

                        <button type="button" @click="ver = !ver"
                            class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white transition hover:bg-slate-700 active:scale-95">
                            <i class="fa-solid" :class="ver ? 'fa-eye-slash' : 'fa-eye'"></i>
                            <span x-text="ver ? 'Ocultar' : 'Mostrar'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="ver" x-cloak>
                    <div class="grid grid-cols-1 gap-4 border-b border-slate-100 bg-white px-5 py-4 sm:grid-cols-2">
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Giro</label>
                                <span class="font-mono text-[11px] font-bold text-indigo-600">{{ (int) $giro }}°</span>
                            </div>
                            <input type="range" min="-180" max="180" step="1" wire:model.live.debounce.60ms="giro" class="w-full accent-indigo-600">
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Inclinación</label>
                                <span class="font-mono text-[11px] font-bold text-indigo-600">{{ (int) $inclinacion }}°</span>
                            </div>
                            <input type="range" min="0" max="90" step="1" wire:model.live.debounce.60ms="inclinacion" class="w-full accent-indigo-600">
                        </div>
                    </div>

                    <div class="bg-[#F8FAFC] p-3 sm:p-4">
                        @if ($v3['ok'])
                            <svg viewBox="0 0 900 400" xmlns="http://www.w3.org/2000/svg" width="100%" preserveAspectRatio="xMidYMid meet"
                                style="display:block;width:100%;height:auto;">

                                <defs>
                                    <linearGradient id="vcSky" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0" stop-color="#F8FAFC" />
                                        <stop offset="1" stop-color="#E8EEF5" />
                                    </linearGradient>
                                </defs>

                                <rect width="900" height="400" fill="url(#vcSky)" />

                                {{-- piso --}}
                                <polygon points="{{ $v3['floor'] }}" fill="#CBD5E1" fill-opacity=".35" />
                                @foreach ($v3['grid'] as $g)
                                    <line x1="{{ $g[0] }}" y1="{{ $g[1] }}" x2="{{ $g[2] }}" y2="{{ $g[3] }}" stroke="#94A3B8" stroke-opacity=".55" stroke-width="1" />
                                @endforeach

                                {{-- vidrio (caras ordenadas de lejos a cerca) --}}
                                @foreach ($v3['polys'] as $q)
                                    <polygon points="{{ $q['pts'] }}" fill="{{ $q['fill'] }}" fill-opacity="{{ $q['op'] }}"
                                        stroke="{{ $q['edge'] ? '#1E4E75' : '#2C5F8A' }}" stroke-opacity="{{ $q['edge'] ? .6 : .18 }}" stroke-width=".6" stroke-linejoin="round" />
                                @endforeach

                                {{-- medidas --}}
                                @php
                                    $aTxt = 'ANCHO ' . $n1($i['c']) . ' cm';
                                    $aW = mb_strlen($aTxt) * 12 * 0.6 + 18;
                                    $hTxt = 'ALTO ' . $n1($i['h']) . ' cm';
                                    $hW = mb_strlen($hTxt) * 12 * 0.6 + 18;
                                @endphp

                                <rect x="{{ $v3['anchoX'] - $aW / 2 }}" y="{{ $v3['anchoY'] + 14 }}" width="{{ $aW }}" height="20" rx="10" fill="#fff" stroke="{{ $dim }}" stroke-width=".8" />
                                <text x="{{ $v3['anchoX'] }}" y="{{ $v3['anchoY'] + 28 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="12" font-weight="500" fill="{{ $dim }}">{{ $aTxt }}</text>

                                <rect x="{{ $v3['altoX'] - $hW - 14 }}" y="{{ $v3['altoY'] - 10 }}" width="{{ $hW }}" height="20" rx="10" fill="#fff" stroke="{{ $dim }}" stroke-width=".8" />
                                <text x="{{ $v3['altoX'] - $hW / 2 - 14 }}" y="{{ $v3['altoY'] + 4 }}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="12" font-weight="500" fill="{{ $dim }}">{{ $hTxt }}</text>
                            </svg>
                        @else
                            <div class="py-20 text-center text-sm italic text-slate-400">
                                Ingresa ancho, flecha y alto para ver el vidrio en 3D
                            </div>
                        @endif
                    </div>

                    <p class="border-t border-slate-200 px-5 py-3 text-[11px] leading-relaxed text-slate-500">
                        Vista referencial: el espesor del vidrio está exagerado solo para que se aprecie el canto.
                        La curvatura (radio {{ $n1($i['r']) }} cm · {{ $n1($i['angulo']) }}°) sí es la calculada.
                    </p>
                </div>

                <div x-show="!ver" x-cloak class="py-16 text-center text-sm italic text-slate-400">Vista 3D oculta</div>
            </section>
        </div>
    </div>

</div>
