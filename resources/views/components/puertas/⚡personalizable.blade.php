<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    public string $name = 'Mi puerta';

    public ?string $currentId = null;

    /** Lista ligera de diseños guardados (id, nombre, fecha). */
    public array $saved = [];

    /** Nombres de perfiles leídos de public/datos.xlsx (para la lista de corte). */
    public array $perfiles = [];

    public function mount(): void
    {
        $this->refreshSaved();
        $this->cargarPerfiles();
    }

    private function cargarPerfiles(): void
    {
        $ruta = public_path('datos.xlsx');

        if (! file_exists($ruta)) {
            return;
        }

        $this->perfiles = collect(Excel::toArray([], $ruta)[0])
            ->pluck(0)
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values()
            ->toArray();
    }

    /**
     * Devuelve un diseño base. El navegador lo carga en el editor.
     */
    public function makeTemplate(string $key): array
    {
        $w = 90;
        $h = 210;
        $m = round($w * 0.12, 1);

        $p = fn (string $type, $x, $y, $wd, $ht, array $o = []) => [
            'id' => 'p'.Str::random(6),
            'type' => $type,
            'x' => round($x, 1),
            'y' => round($y, 1),
            'w' => round($wd, 1),
            'h' => round($ht, 1),
        ] + $o;

        $hinges = [
            $p('hinge', 1, 22, 3, 16),
            $p('hinge', 1, $h / 2 - 8, 3, 16),
            $p('hinge', 1, $h - 38, 3, 16),
        ];
        $handle = $p('handle', $w - 11, $h * 0.48, 5, 16);

        // Valores por defecto de cálculo: material 7852, bisagra 3x3, sin sobreluz.
        $door = fn (string $color, string $frame, array $parts) => [
            'width' => $w,
            'height' => $h,
            'material' => 7852,
            'tipoBisagra' => '3x3',
            'conSobreluz' => false,
            'altoSobreluz' => 30,
            'profile' => 6,
            'color' => $color,
            'frameColor' => $frame,
            'parts' => $parts,
        ];

        return match ($key) {
            'clasica' => (function () use ($door, $p, $hinges, $handle, $w, $h, $m) {
                $pw = ($w - 3 * $m) / 2;
                $top = $h * 0.40;
                $bottom = $h * 0.42;
                $y2 = $m + $top + $m;

                return $door('#c4c9ce', '#8d949b', [
                    $p('panel', $m, $m, $pw, $top),
                    $p('panel', 2 * $m + $pw, $m, $pw, $top),
                    $p('panel', $m, $y2, $pw, $bottom),
                    $p('panel', 2 * $m + $pw, $y2, $pw, $bottom),
                    ...$hinges,
                    $handle,
                ]);
            })(),
            'moderna' => $door('#1f2327', '#12151a', [
                $p('glass', $w * 0.62, 15, $w * 0.18, $h - 30, ['cols' => 1, 'rows' => 1]),
                $p('bar', $w * 0.5, $h * 0.42, 3, 60),
                ...$hinges,
            ]),
            'colonial' => $door('#6b4f3a', '#43301f', [
                $p('arch', $m, $m, $w - 2 * $m, $h * 0.35, ['fill' => '#bfe3f2', 'op' => 0.8, 'sw' => 1]),
                $p('panel', $m, $h * 0.42, $w - 2 * $m, $h * 0.24),
                $p('panel', $m, $h * 0.69, $w - 2 * $m, $h * 0.24),
                ...$hinges,
                $handle,
            ]),
            'vidriera' => $door('#3b4249', '#22272c', [
                $p('glass', $m, $m, $w - 2 * $m, $h * 0.6, ['cols' => 3, 'rows' => 4]),
                $p('rail', $m, $h * 0.65, $w - 2 * $m, 5),
                $p('panel', $m, $h * 0.68, $w - 2 * $m, $h * 0.26),
                $p('plate', $m, $h - 26, $w - 2 * $m, 18),
                ...$hinges,
                $handle,
            ]),
            default => $door('#c4c9ce', '#8d949b', []),
        };
    }

    public function saveDesign(array $doc): bool
    {
        $this->validate(['name' => 'required|string|max:60']);

        $color = ['regex:/^#[0-9a-fA-F]{3,8}$/'];
        $colorOrNone = ['nullable', 'regex:/^(#[0-9a-fA-F]{3,8}|none)$/'];

        $data = Validator::make($doc, [
            'width' => 'required|numeric|between:50,250',
            'height' => 'required|numeric|between:150,320',
            'material' => 'required|in:7830,7852',
            'tipoBisagra' => 'required|in:3x3,mariposa',
            'conSobreluz' => 'required|boolean',
            'altoSobreluz' => 'required|numeric|between:10,100',
            'profile' => 'required|numeric|between:0,15',
            'color' => ['required', ...$color],
            'frameColor' => ['required', ...$color],
            'parts' => ['present', 'array', 'max:200'],
            'parts.*.id' => 'required|string|max:24|regex:/^[\w-]+$/',
            'parts.*.type' => 'required|in:panel,arch,glass,round,handle,hinge,peephole,slot,plate,bar,rail,draw',
            'parts.*.x' => 'required|numeric',
            'parts.*.y' => 'required|numeric',
            'parts.*.w' => 'nullable|numeric',
            'parts.*.h' => 'nullable|numeric',
            'parts.*.fill' => $colorOrNone,
            'parts.*.stroke' => $colorOrNone,
            'parts.*.sw' => 'nullable|numeric|between:0,10',
            'parts.*.op' => 'nullable|numeric|between:0,1',
            'parts.*.cols' => 'nullable|integer|between:1,8',
            'parts.*.rows' => 'nullable|integer|between:1,8',
            'parts.*.pts' => 'nullable|array|max:3000',
            'parts.*.pts.*' => 'array|size:2',
            'parts.*.pts.*.*' => 'numeric',
        ])->validate();

        $id = $this->currentId ?? Str::random(8);

        // Para producción: cambia la sesión por un modelo Eloquent (columna JSON).
        $designs = session('door_designs', []);
        $designs[$id] = [
            'id' => $id,
            'name' => $this->name,
            'updated' => now()->format('d/m H:i'),
            'doc' => $data,
        ];
        session(['door_designs' => $designs]);

        $this->currentId = $id;
        $this->refreshSaved();

        return true;
    }

    public function loadDesign(string $id): ?array
    {
        $design = session('door_designs', [])[$id] ?? null;

        if (! $design) {
            return null;
        }

        $this->name = $design['name'];
        $this->currentId = $id;

        return $design['doc'];
    }

    public function removeDesign(string $id): void
    {
        $designs = session('door_designs', []);
        unset($designs[$id]);
        session(['door_designs' => $designs]);

        if ($this->currentId === $id) {
            $this->currentId = null;
        }

        $this->refreshSaved();
    }

    public function newDesign(): void
    {
        $this->currentId = null;
        $this->name = 'Mi puerta';
    }

    private function refreshSaved(): void
    {
        $this->saved = collect(session('door_designs', []))
            ->map(fn ($d) => ['id' => $d['id'], 'name' => $d['name'], 'updated' => $d['updated']])
            ->values()
            ->all();
    }
};
?>

<div
    x-data="doorEditor"
    x-on:keydown.window="key($event)"
    class="space-y-3 rounded-xl bg-white p-3 text-sm text-zinc-800"
>
    {{-- Barra superior --}}
    <div class="flex flex-wrap items-center gap-2">
        <input
            type="text"
            wire:model="name"
            maxlength="60"
            class="w-52 rounded-md border border-zinc-300 bg-white px-2 py-1.5"
            aria-label="Nombre del diseño"
        >
        <button type="button" x-on:click="save()" class="rounded-md bg-blue-600 px-3 py-1.5 font-medium text-white hover:bg-blue-700">Guardar diseño</button>
        <button type="button" wire:click="newDesign" x-on:click="setDoc(blank())" class="rounded-md border border-zinc-300 px-3 py-1.5">Nuevo</button>

        <span class="mx-1 h-5 w-px bg-zinc-300"></span>

        <button type="button" x-on:click="undo()" :disabled="!hist.length" class="rounded-md border border-zinc-300 px-3 py-1.5 disabled:opacity-40">Deshacer</button>
        <button type="button" x-on:click="redo()" :disabled="!fut.length" class="rounded-md border border-zinc-300 px-3 py-1.5 disabled:opacity-40">Rehacer</button>

        <span class="mx-1 h-5 w-px bg-zinc-300"></span>

        <label class="flex items-center gap-1.5"><input type="checkbox" x-model="grid"> Cuadrícula</label>
        <label class="flex items-center gap-1.5"><input type="checkbox" x-model="snap"> Ajustar a 2,5 cm</label>
        <label class="flex items-center gap-1.5"><input type="checkbox" x-model="dims"> Medidas</label>

        <span class="ml-auto flex gap-2">
            <button type="button" x-on:click="exportSvg()" class="rounded-md border border-zinc-300 px-3 py-1.5">Descargar SVG</button>
            <button type="button" x-on:click="exportPng()" class="rounded-md border border-zinc-300 px-3 py-1.5">Descargar PNG</button>
        </span>
    </div>

    @if ($errors->any())
        <p class="text-red-600">No se pudo guardar: {{ $errors->first() }}</p>
    @endif
    <p x-show="msg" x-text="msg" class="text-emerald-600"></p>

    {{-- Editor (Alpine controla este bloque; Livewire no lo vuelve a pintar) --}}
    <div wire:ignore class="grid gap-3 lg:grid-cols-[92px_minmax(0,1fr)_290px]">

        {{-- Herramientas --}}
        <div class="flex flex-row flex-wrap gap-1.5 lg:flex-col">
            <template x-for="t in tools" :key="t[0]">
                <button
                    type="button"
                    x-on:click="tool = t[0]"
                    :title="t[2]"
                    :class="tool === t[0] ? 'bg-blue-600 text-white' : 'bg-zinc-100 hover:bg-zinc-200'"
                    class="flex w-[84px] items-center gap-2 rounded-md px-2 py-1.5 text-left"
                >
                    <span class="w-4 text-center text-base leading-none" x-text="t[1]"></span>
                    <span class="text-xs" x-text="t[3]"></span>
                </button>
            </template>

            <div x-show="tool === 'pen'" class="mt-2 space-y-1.5 rounded-md bg-zinc-100 p-2">
                <label class="block text-xs">Color
                    <input type="color" x-model="penColor" class="mt-0.5 h-7 w-full cursor-pointer">
                </label>
                <label class="block text-xs">Grosor
                    <input type="range" min="0.3" max="4" step="0.1" x-model.number="penSize" class="w-full">
                </label>
            </div>
        </div>

        {{-- Lienzo --}}
        <div class="rounded-xl border border-zinc-300 bg-sky-50">
            <svg
                x-ref="svg"
                x-effect="$el.setAttribute('viewBox', vb)"
                x-on:pointerdown.prevent="down($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="up($event)"
                x-on:pointercancel="up($event)"
                class="block h-[72vh] w-full select-none"
                style="touch-action: none"
                :style="tool === 'select' ? 'cursor: default' : 'cursor: crosshair'"
                role="img"
                aria-label="Lienzo de diseño de la puerta"
            >
                <g x-html="scene()"></g>
            </svg>
        </div>

        {{-- Inspector --}}
        <div class="space-y-4">
            <section class="space-y-2 rounded-lg border border-zinc-300 p-3">
                <h3 class="font-semibold">Puerta</h3>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs">Ancho (cm)
                        <input type="number" min="50" max="250" x-model.number="doc.width" x-on:change="commit()" class="mt-0.5 w-full rounded border border-zinc-300 bg-white px-2 py-1">
                    </label>
                    <label class="text-xs">Alto (cm)
                        <input type="number" min="150" max="320" x-model.number="doc.height" x-on:change="commit()" class="mt-0.5 w-full rounded border border-zinc-300 bg-white px-2 py-1">
                    </label>
                </div>

                {{-- Cálculo: material, bisagra y sobreluz --}}
                <label class="block text-xs">Material
                    <select x-model.number="doc.material" x-on:change="commit()" class="mt-0.5 w-full cursor-pointer rounded border border-zinc-300 bg-white px-2 py-1.5">
                        <option value="7830">Canal 60 - 7830</option>
                        <option value="7852">Rectangular 60 - 7852</option>
                    </select>
                </label>
                <label class="block text-xs">Bisagra
                    <select x-model="doc.tipoBisagra" x-on:change="commit()" class="mt-0.5 w-full cursor-pointer rounded border border-zinc-300 bg-white px-2 py-1.5">
                        <option value="3x3">Bisagra 3 x 3 (luz 0.6)</option>
                        <option value="mariposa">Bisagra mariposa (luz 0.8)</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" x-model="doc.conSobreluz" x-on:change="commit()">
                    Puerta con sobreluz
                </label>
                <label x-show="doc.conSobreluz" class="block text-xs">Alto del sobreluz (cm)
                    <input type="number" min="10" max="100" step="0.5" x-model.number="doc.altoSobreluz" x-on:change="commit()" class="mt-0.5 w-full rounded border border-zinc-300 bg-white px-2 py-1">
                </label>

                <div>
                    <p class="mb-1 text-xs">Acabado del aluminio</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="f in finishes" :key="f[0]">
                            <button type="button" x-on:click="applyFinish(f)" :title="f[0]" :aria-label="f[0]" class="h-7 w-7 rounded-full border border-zinc-400" :style="'background:' + f[1]"></button>
                        </template>
                    </div>
                </div>
                <label class="block text-xs">Perfil perimetral visual (cm)
                    <input type="number" min="0" max="15" step="0.5" x-model.number="doc.profile" x-on:change="commit()" class="mt-0.5 w-full rounded border border-zinc-300 bg-white px-2 py-1">
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs">Hoja
                        <input type="color" x-model="doc.color" x-on:change="commit()" class="mt-0.5 h-8 w-full cursor-pointer">
                    </label>
                    <label class="text-xs">Marco
                        <input type="color" x-model="doc.frameColor" x-on:change="commit()" class="mt-0.5 h-8 w-full cursor-pointer">
                    </label>
                </div>
                <div>
                    <p class="mb-1 text-xs">Plantillas</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="t in templates" :key="t[0]">
                            <button type="button" x-on:click="loadTemplate(t[0])" class="rounded-md bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200" x-text="t[1]"></button>
                        </template>
                    </div>
                </div>
            </section>

            <section class="space-y-2 rounded-lg border border-zinc-300 p-3">
                <h3 class="font-semibold">Pieza seleccionada</h3>

                <p x-show="!sel" class="text-xs text-zinc-500">Elige una pieza en el lienzo, o escoge una herramienta y arrastra sobre la puerta para dibujarla.</p>

                <template x-if="sel">
                    <div class="space-y-2">
                        <p class="text-xs font-medium" x-text="label(sel.type)"></p>

                        <div x-show="sel.type !== 'draw'" class="grid grid-cols-4 gap-1.5">
                            <label class="text-xs">X<input type="number" step="0.5" x-model.number="sel.x" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-1 py-1"></label>
                            <label class="text-xs">Y<input type="number" step="0.5" x-model.number="sel.y" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-1 py-1"></label>
                            <label class="text-xs">Ancho<input type="number" step="0.5" min="1" x-model.number="sel.w" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-1 py-1"></label>
                            <label class="text-xs">Alto<input type="number" step="0.5" min="1" x-model.number="sel.h" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-1 py-1"></label>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <label x-show="sel.type !== 'draw'" class="text-xs">Relleno
                                <input type="color" x-model="sel.fill" x-on:change="commit()" class="mt-0.5 h-8 w-full cursor-pointer">
                            </label>
                            <label class="text-xs">Contorno
                                <input type="color" x-model="sel.stroke" x-on:change="commit()" class="mt-0.5 h-8 w-full cursor-pointer">
                            </label>
                        </div>

                        <label x-show="sel.type !== 'draw'" class="block text-xs">Opacidad del relleno
                            <input type="range" min="0.05" max="1" step="0.05" x-model.number="sel.op" x-on:change="commit()" class="w-full">
                        </label>
                        <label class="block text-xs">Grosor de línea
                            <input type="range" min="0.2" max="4" step="0.1" x-model.number="sel.sw" x-on:change="commit()" class="w-full">
                        </label>

                        <div x-show="sel.type === 'glass' || sel.type === 'round'" class="grid grid-cols-2 gap-2">
                            <label class="text-xs">Columnas
                                <input type="number" min="1" max="8" x-model.number="sel.cols" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-2 py-1">
                            </label>
                            <label class="text-xs">Filas
                                <input type="number" min="1" max="8" x-model.number="sel.rows" x-on:change="commit()" class="w-full rounded border border-zinc-300 bg-white px-2 py-1">
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button type="button" x-on:click="dup()" class="rounded-md bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200">Duplicar</button>
                            <button type="button" x-on:click="mirror()" class="rounded-md bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200">Copia en espejo</button>
                            <button type="button" x-on:click="toFront()" class="rounded-md bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200">Traer al frente</button>
                            <button type="button" x-on:click="toBack()" class="rounded-md bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200">Enviar atrás</button>
                            <button type="button" x-on:click="del()" class="rounded-md bg-red-100 px-2 py-1 text-xs text-red-700 hover:bg-red-200">Eliminar</button>
                        </div>
                    </div>
                </template>
            </section>

            <p class="text-xs text-zinc-500">
                Atajos: V seleccionar, P lápiz, R panel, A arco, G vidrio, O ojo de buey, H manija, B bisagra, M mirilla, S buzón, K chapa, T tirador, L travesaño. Supr elimina, flechas mueven, Ctrl+Z / Ctrl+Y deshacen y rehacen, Ctrl+D duplica.
            </p>
        </div>
    </div>

    {{-- Lista de corte (se recalcula sola al cambiar medidas, material, bisagra o sobreluz) --}}
    <section wire:ignore class="rounded-lg border border-zinc-300 p-3">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold">Lista de corte</h3>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-zinc-100 px-2.5 py-1">Medida de cálculo: <b x-text="calc.ancho + ' × ' + calc.alto"></b></span>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1">Luz lados: <b x-text="calc.luzLados + ' cm'"></b></span>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1">Perfiles: <b x-text="totales.perfiles"></b></span>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1">Vidrios: <b x-text="totales.vidrios"></b></span>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <template x-for="g in cortes" :key="g.n">
                <div class="rounded-md border border-zinc-200">
                    <p class="border-b border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide" x-text="g.n"></p>
                    <template x-for="(f, i) in g.filas" :key="i">
                        <div class="flex items-start justify-between gap-2 border-t border-zinc-100 px-2.5 py-2 first:border-t-0">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium" x-text="f.t"></p>
                                <p class="text-[11px] text-zinc-500" x-text="f.d"></p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-mono text-xs" :class="f.ok ? '' : 'font-bold text-red-600'" x-text="f.txt"></p>
                                <p class="text-[11px] text-emerald-600" x-text="'× ' + f.c"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <p x-show="!totales.valido" class="mt-2 text-xs text-red-600">Hay medidas negativas o en cero: revisa el ancho, el alto o el alto del sobreluz.</p>
    </section>

    {{-- Diseños guardados --}}
    <section class="rounded-lg border border-zinc-300 p-3">
        <h3 class="mb-2 font-semibold">Diseños guardados</h3>
        @forelse ($saved as $item)
            <div class="flex items-center justify-between gap-2 border-t border-zinc-200 py-1.5 first:border-t-0">
                <button type="button" x-on:click="openSaved('{{ $item['id'] }}')" class="text-left hover:underline">
                    {{ $item['name'] }} <span class="text-xs text-zinc-500">{{ $item['updated'] }}</span>
                </button>
                <button type="button" wire:click="removeDesign('{{ $item['id'] }}')" wire:confirm="¿Eliminar este diseño?" class="text-xs text-red-600 hover:underline">Eliminar</button>
            </div>
        @empty
            <p class="text-xs text-zinc-500">Todavía no guardaste ningún diseño.</p>
        @endforelse
    </section>
</div>

@script
<script>
Alpine.data('doorEditor', () => {
    // Estilo por defecto de cada tipo de pieza (medidas en cm).
    const D = {
        panel:    { fill: '#000000', op: .14, stroke: '#2a1a0a', sw: .5 },
        arch:     { fill: '#000000', op: .14, stroke: '#2a1a0a', sw: .5 },
        glass:    { fill: '#bfe3f2', op: .8,  stroke: '#2a1a0a', sw: 1, cols: 2, rows: 3 },
        round:    { fill: '#bfe3f2', op: .8,  stroke: '#b08d57', sw: 1.6, cols: 1, rows: 1 },
        handle:   { fill: '#d4af37', op: 1,   stroke: '#6b5311', sw: .4 },
        hinge:    { fill: '#9ca3af', op: 1,   stroke: '#374151', sw: .3 },
        peephole: { fill: '#d4af37', op: 1,   stroke: '#6b5311', sw: .3 },
        slot:     { fill: '#9ca3af', op: 1,   stroke: '#374151', sw: .4 },
        plate:    { fill: '#c9ced6', op: 1,   stroke: '#4b5563', sw: .4 },
        bar:      { fill: '#d5d9de', op: 1,   stroke: '#4b5563', sw: .4 },
        rail:     { fill: '#c9ced6', op: 1,   stroke: '#4b5563', sw: .4 },
        draw:     { fill: 'none',    op: 1,   stroke: '#111827', sw: .8 },
    };
    const FIXED = { handle: [5, 16], hinge: [3, 16], peephole: [3, 3] };      // se colocan con un clic
    const SIZE  = { panel: [30, 60], arch: [30, 60], glass: [25, 60], round: [30, 30], slot: [30, 5], plate: [60, 18], bar: [3, 60], rail: [60, 5] };
    const NAMES = { panel: 'Panel', arch: 'Panel en arco', glass: 'Vidrio', round: 'Ojo de buey', handle: 'Manija', hinge: 'Bisagra', peephole: 'Mirilla', slot: 'Buzón', plate: 'Chapa protectora', bar: 'Tirador de barra', rail: 'Travesaño', draw: 'Trazo a mano' };

    // Constantes de cálculo (mismas que la calculadora de puertas).
    const K = { tubo: 2.5, canal: 2.2, cuadrado: 3.8, paflon: 8.2, luzArriba: .5, luzAbajo: 1, descuento: .5, luz3x3: .6, luzMariposa: .8 };

    const n = v => { v = Number(v); return Number.isFinite(v) ? Math.round(v * 100) / 100 : 0 };
    const col = (v, d = '#000000') => /^#[0-9a-f]{3,8}$/i.test(v) ? v : (v === 'none' ? 'none' : d);
    const sid = v => String(v).replace(/[^\w-]/g, '');
    const cnt = v => Math.min(8, Math.max(1, parseInt(v) || 1));
    const blankDoc = () => ({ width: 90, height: 210, material: 7852, tipoBisagra: '3x3', conSobreluz: false, altoSobreluz: 30, profile: 6, color: '#c4c9ce', frameColor: '#8d949b', parts: [] });

    return {
        doc: blankDoc(),
        tool: 'select', selId: null, snap: true, grid: true, dims: true,
        penColor: '#111827', penSize: .8,
        hist: [], fut: [], last: '', drag: null, msg: '',

        tools: [
            ['select',   '↖', 'Seleccionar (V)',       'Mover'],
            ['pen',      '✎', 'Lápiz (P)',             'Lápiz'],
            ['panel',    '▭', 'Panel (R)',             'Panel'],
            ['arch',     '∩', 'Panel en arco (A)',     'Arco'],
            ['glass',    '▦', 'Vidrio (G)',            'Vidrio'],
            ['round',    '◯', 'Ojo de buey (O)',       'Ojo buey'],
            ['handle',   '⌐', 'Manija (H)',            'Manija'],
            ['hinge',    '▮', 'Bisagra (B)',           'Bisagra'],
            ['peephole', '◉', 'Mirilla (M)',           'Mirilla'],
            ['slot',     '▬', 'Buzón (S)',             'Buzón'],
            ['plate',    '▤', 'Chapa protectora (K)',  'Chapa'],
            ['bar',      '❘', 'Tirador de barra (T)',  'Tirador'],
            ['rail',     '═', 'Travesaño (L)',         'Travesaño'],
        ],
        finishes: [['Natural', '#c4c9ce', '#8d949b'], ['Blanco', '#f1f2f4', '#c9cdd2'], ['Negro', '#1f2327', '#12151a'], ['Antracita', '#3b4249', '#22272c'], ['Bronce', '#6b4f3a', '#43301f'], ['Champagne', '#cdbb9a', '#a8977a']],
        templates: [['clasica', 'Clásica'], ['moderna', 'Moderna'], ['colonial', 'Colonial'], ['vidriera', 'Vidriera'], ['vacia', 'En blanco']],

        // ---------- Ciclo de vida ----------
        async init() {
            await this.loadTemplate('clasica', true);
        },

        // ---------- Datos derivados ----------
        get W() { return Math.min(250, Math.max(50, n(this.doc.width) || 90)) },
        get H() { return Math.min(320, Math.max(150, n(this.doc.height) || 210)) },
        get vb() { return `-25 -25 ${this.W + 50} ${this.H + 50}` },
        get sel() { return this.doc.parts.find(p => p.id === this.selId) },

        // ---------- Cálculo de descuentos / lista de corte ----------
        nombrePerfil(codigo) {
            const lista = this.$wire.perfiles || [];
            return lista.find(p => String(p).includes(String(codigo))) || String(codigo);
        },
        get calc() {
            const d = this.doc;
            const mat = Number(d.material) === 7830 ? 7830 : 7852;
            const perfil = mat === 7852 ? K.tubo : K.canal;
            const luzL = d.tipoBisagra === 'mariposa' ? K.luzMariposa : K.luz3x3;
            const aC = this.W - K.descuento, hC = this.H - K.descuento;
            const so = !!d.conSobreluz, hS = Math.max(0, n(d.altoSobreluz));
            const altoHoja = so ? hC - hS : hC;
            const anchoHoja = aC - perfil * 2 - luzL;
            return { mat, perfil, luzLados: luzL, aC, hC, so, hS, altoHoja, anchoHoja, ancho: n(aC), alto: n(hC) };
        },
        get cortes() {
            const c = this.calc, f1 = v => ({ txt: v.toFixed(1) + ' cm', ok: v > 0 });
            const vid = (a, b) => ({ txt: `${a.toFixed(2)} x ${b.toFixed(2)} cm`, ok: a > 0 && b > 0 });
            const nom = k => this.nombrePerfil(k);
            const lado = c.anchoHoja - K.cuadrado * 2;

            const marco = [
                { t: nom(c.mat), d: 'Lados', ...f1(c.hC), c: 2 },
                { t: nom(c.mat), d: 'Arriba', ...f1(c.aC - c.perfil * 2), c: c.so ? 2 : 1 },
            ];
            const hoja = [
                { t: nom(5414), d: 'Arriba y abajo', ...f1(lado), c: 2 },
                { t: nom(5414), d: 'Lados', ...f1(c.altoHoja - K.luzArriba - K.luzAbajo - c.perfil), c: 2 },
                { t: nom(5227), d: 'Travesaño', ...f1(lado), c: 1 },
            ];
            const altoVid = (c.altoHoja - (K.luzArriba + K.luzAbajo) - K.cuadrado * 2 - K.paflon - c.perfil) / 2;
            const vidrios = [{ t: 'Vidrio', d: 'Alto × ancho', ...vid(altoVid, lado), c: 2 }];
            if (c.so) vidrios.push({ t: 'Vidrio sobreluz', d: 'Alto × ancho', ...vid(c.hS - c.perfil - .5, c.aC - c.perfil * 2), c: 1 });
            const accesorios = [
                { t: 'Bisagras', d: 'Tipo', txt: this.doc.tipoBisagra === 'mariposa' ? 'Mariposa' : '3x3', ok: true, c: 3 },
                { t: 'Chapas', d: 'Cerradura', txt: 'Unidad', ok: true, c: 1 },
            ];

            return [
                { n: 'Marco', filas: marco },
                { n: 'Hoja', filas: hoja },
                { n: 'Vidrios', filas: vidrios },
                { n: 'Accesorios', filas: accesorios },
            ];
        },
        get totales() {
            const g = this.cortes, sum = a => a.reduce((s, f) => s + f.c, 0);
            return {
                perfiles: sum(g[0].filas) + sum(g[1].filas),
                vidrios: sum(g[2].filas),
                valido: g.every(x => x.filas.every(f => f.ok)),
            };
        },

        label(t) { return NAMES[t] || t },
        applyFinish(f) { this.doc.color = f[1]; this.doc.frameColor = f[2]; this.commit(); },
        uid() { return 'p' + Math.random().toString(36).slice(2, 9) },
        blank() { return blankDoc() },
        norm(p) { return { ...D[p.type], ...p } },
        byId(id) { return this.doc.parts.find(p => p.id === id) },
        snapV(v) { return this.snap ? Math.round(v / 2.5) * 2.5 : Math.round(v * 10) / 10 },

        bbox(p) {
            if (p.type === 'draw') {
                const xs = p.pts.map(a => a[0]), ys = p.pts.map(a => a[1]);
                const x0 = Math.min(...xs), y0 = Math.min(...ys);
                return { x: p.x + x0, y: p.y + y0, w: Math.max(...xs) - x0, h: Math.max(...ys) - y0 };
            }
            return { x: p.x, y: p.y, w: p.w, h: p.h };
        },

        pt(e) {
            const s = this.$refs.svg, p = s.createSVGPoint();
            p.x = e.clientX; p.y = e.clientY;
            const q = p.matrixTransform(s.getScreenCTM().inverse());
            return { x: q.x, y: q.y };
        },

        // ---------- Historial ----------
        commit() {
            const now = JSON.stringify(this.doc);
            if (now === this.last) return;
            if (this.last) this.hist.push(this.last);
            if (this.hist.length > 100) this.hist.shift();
            this.fut = [];
            this.last = now;
        },
        undo() {
            if (!this.hist.length) return;
            this.fut.push(this.last);
            this.last = this.hist.pop();
            this.doc = JSON.parse(this.last);
            this.selId = null;
        },
        redo() {
            if (!this.fut.length) return;
            this.hist.push(this.last);
            this.last = this.fut.pop();
            this.doc = JSON.parse(this.last);
            this.selId = null;
        },
        setDoc(d, reset = false) {
            d.parts = (d.parts || []).map(p => this.norm(p));
            d.material = Number(d.material) === 7830 ? 7830 : 7852;
            d.tipoBisagra = d.tipoBisagra === 'mariposa' ? 'mariposa' : '3x3';
            d.conSobreluz = !!d.conSobreluz;
            d.altoSobreluz = n(d.altoSobreluz) || 30;
            d.profile = d.profile ?? 6;
            this.doc = d;
            this.selId = null;
            this.tool = 'select';
            if (reset) { this.hist = []; this.fut = []; this.last = JSON.stringify(this.doc); }
            else this.commit();
        },

        // ---------- Llamadas a Livewire ----------
        async loadTemplate(key, reset = false) {
            this.setDoc(await this.$wire.makeTemplate(key), reset);
        },
        async save() {
            this.msg = '';
            const ok = await this.$wire.saveDesign(JSON.parse(JSON.stringify(this.doc)));
            this.msg = ok ? 'Diseño guardado' : '';
            setTimeout(() => this.msg = '', 2500);
        },
        async openSaved(id) {
            const d = await this.$wire.loadDesign(id);
            if (d) this.setDoc(d);
        },

        // ---------- Edición de piezas ----------
        dup() {
            const s = this.sel; if (!s) return;
            const c = JSON.parse(JSON.stringify(s));
            c.id = this.uid(); c.x = n(c.x + 5); c.y = n(c.y + 5);
            this.doc.parts.push(c); this.selId = c.id; this.commit();
        },
        mirror() {
            const s = this.sel; if (!s) return;
            const c = JSON.parse(JSON.stringify(s));
            c.id = this.uid();
            if (c.type === 'draw') {
                const xs = c.pts.map(a => a[0]), mn = Math.min(...xs), mx = Math.max(...xs);
                c.pts = c.pts.map(([a, b]) => [n(mn + mx - a), b]);
                c.x = n(this.W - c.x - mx - mn);
            } else {
                c.x = n(this.W - c.x - c.w);
            }
            this.doc.parts.push(c); this.selId = c.id; this.commit();
        },
        del() {
            if (!this.selId) return;
            this.doc.parts = this.doc.parts.filter(p => p.id !== this.selId);
            this.selId = null; this.commit();
        },
        toFront() {
            const s = this.sel; if (!s) return;
            this.doc.parts = [...this.doc.parts.filter(p => p.id !== s.id), s]; this.commit();
        },
        toBack() {
            const s = this.sel; if (!s) return;
            this.doc.parts = [s, ...this.doc.parts.filter(p => p.id !== s.id)]; this.commit();
        },

        // ---------- Ratón / táctil ----------
        down(e) {
            const p = this.pt(e);
            this.$refs.svg.setPointerCapture(e.pointerId);
            const before = JSON.stringify(this.doc);
            const hEl = e.target.closest('[data-h]');
            const idEl = e.target.closest('[data-id]');

            if (this.tool === 'select') {
                if (hEl && this.sel) {
                    this.drag = { mode: 'resize', dir: hEl.dataset.h, start: { ...this.bbox(this.sel) }, p0: p, before };
                } else if (idEl) {
                    this.selId = idEl.dataset.id;
                    const s = this.sel;
                    this.drag = { mode: 'move', p0: p, ox: s.x, oy: s.y, before };
                } else {
                    this.selId = null;
                }
                return;
            }

            if (this.tool === 'pen') {
                const part = this.norm({ id: this.uid(), type: 'draw', x: 0, y: 0, pts: [[n(p.x), n(p.y)]], stroke: this.penColor, sw: this.penSize });
                this.doc.parts.push(part);
                this.selId = null;
                this.drag = { mode: 'pen', id: part.id, before };
                return;
            }

            const t = this.tool, id = this.uid();
            if (FIXED[t]) {
                const [w, h] = FIXED[t];
                this.doc.parts.push(this.norm({ id, type: t, x: this.snapV(p.x - w / 2), y: this.snapV(p.y - h / 2), w, h }));
                this.selId = id; this.tool = 'select'; this.commit();
                return;
            }
            const q = { x: this.snapV(p.x), y: this.snapV(p.y) };
            this.doc.parts.push(this.norm({ id, type: t, x: q.x, y: q.y, w: 0, h: 0 }));
            this.selId = id;
            this.drag = { mode: 'draw', id, q, before };
        },

        move(e) {
            const d = this.drag; if (!d) return;
            const p = this.pt(e);

            if (d.mode === 'move') {
                const s = this.sel; if (!s) return;
                s.x = n(this.snapV(d.ox + p.x - d.p0.x));
                s.y = n(this.snapV(d.oy + p.y - d.p0.y));
            } else if (d.mode === 'resize') {
                const s = this.sel; if (!s) return;
                const dx = p.x - d.p0.x, dy = p.y - d.p0.y, b = d.start;
                let { x, y, w, h } = b;
                if (d.dir.includes('e')) w = b.w + dx;
                if (d.dir.includes('s')) h = b.h + dy;
                if (d.dir.includes('w')) { x = b.x + dx; w = b.w - dx; }
                if (d.dir.includes('n')) { y = b.y + dy; h = b.h - dy; }
                x = this.snapV(x); y = this.snapV(y); w = this.snapV(w); h = this.snapV(h);
                if (w < 2) { w = 2; if (d.dir.includes('w')) x = b.x + b.w - 2; }
                if (h < 2) { h = 2; if (d.dir.includes('n')) y = b.y + b.h - 2; }
                Object.assign(s, { x: n(x), y: n(y), w: n(w), h: n(h) });
            } else if (d.mode === 'draw') {
                const s = this.byId(d.id);
                const x2 = this.snapV(p.x), y2 = this.snapV(p.y);
                Object.assign(s, { x: Math.min(d.q.x, x2), y: Math.min(d.q.y, y2), w: Math.abs(x2 - d.q.x), h: Math.abs(y2 - d.q.y) });
            } else if (d.mode === 'pen') {
                const s = this.byId(d.id), l = s.pts[s.pts.length - 1];
                if (Math.hypot(p.x - l[0], p.y - l[1]) > 1) s.pts.push([n(p.x), n(p.y)]);
            }
        },

        up() {
            const d = this.drag; if (!d) return;
            this.drag = null;

            if (d.mode === 'draw') {
                const s = this.byId(d.id);
                if (s.w < 3 && s.h < 3) {            // un clic simple crea la pieza con tamaño estándar
                    const [w, h] = SIZE[s.type];
                    Object.assign(s, { w, h, x: this.snapV(d.q.x - w / 2), y: this.snapV(d.q.y - h / 2) });
                }
                this.tool = 'select';
            } else if (d.mode === 'pen') {
                const s = this.byId(d.id);
                if (s.pts.length < 2) s.pts.push([n(s.pts[0][0] + .01), s.pts[0][1]]);
                this.selId = s.id;
            }
            this.commit();
        },

        key(e) {
            if (/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) return;
            const k = e.key.toLowerCase(), mod = e.ctrlKey || e.metaKey;

            if (mod && k === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
            if (mod && k === 'y') { e.preventDefault(); this.redo(); return; }
            if (mod && k === 'd') { e.preventDefault(); this.dup(); return; }
            if (k === 'delete' || k === 'backspace') { if (this.sel) { e.preventDefault(); this.del(); } return; }
            if (k === 'escape') { this.tool = 'select'; this.selId = null; return; }
            if (k.startsWith('arrow') && this.sel) {
                e.preventDefault();
                const st = e.shiftKey ? 10 : 1;
                this.sel.x = n(this.sel.x + (k === 'arrowright' ? st : k === 'arrowleft' ? -st : 0));
                this.sel.y = n(this.sel.y + (k === 'arrowdown' ? st : k === 'arrowup' ? -st : 0));
                this.commit();
                return;
            }
            const map = { v: 'select', p: 'pen', r: 'panel', a: 'arch', g: 'glass', o: 'round', h: 'handle', b: 'hinge', m: 'peephole', s: 'slot', k: 'plate', t: 'bar', l: 'rail' };
            if (!mod && map[k]) this.tool = map[k];
        },

        // ---------- Dibujo ----------
        pathD(pts) {
            if (pts.length < 3) return 'M' + pts.map(a => `${n(a[0])} ${n(a[1])}`).join('L');
            let d = `M${n(pts[0][0])} ${n(pts[0][1])}`;
            for (let i = 1; i < pts.length - 1; i++) {
                const mx = (pts[i][0] + pts[i + 1][0]) / 2, my = (pts[i][1] + pts[i + 1][1]) / 2;
                d += `Q${n(pts[i][0])} ${n(pts[i][1])} ${n(mx)} ${n(my)}`;
            }
            const l = pts[pts.length - 1];
            return d + `L${n(l[0])} ${n(l[1])}`;
        },
        arch(x, y, w, h) {
            const r = Math.min(w / 2, h);
            return `M${x} ${y + h}V${y + r}A${r} ${r} 0 0 1 ${x + w} ${y + r}V${y + h}Z`;
        },
        muntins(x, y, w, h, cols, rows, stroke, sw) {
            let m = '';
            for (let c = 1; c < cols; c++) m += `M${n(x + w * c / cols)} ${y}V${n(y + h)}`;
            for (let r = 1; r < rows; r++) m += `M${x} ${n(y + h * r / rows)}H${n(x + w)}`;
            return `<path d="${m}" fill="none" stroke="${stroke}" stroke-width="${n(Math.max(sw * .9, .4))}"/>`;
        },

        shape(p) {
            const x = n(p.x), y = n(p.y), w = Math.max(n(p.w), 0), h = Math.max(n(p.h), 0);
            const fill = col(p.fill), stroke = col(p.stroke, '#111827'), sw = n(p.sw), op = n(p.op);
            const st = `fill="${fill}" fill-opacity="${op}" stroke="${stroke}" stroke-width="${sw}"`;
            const ln = `fill="none" stroke="${stroke}" stroke-opacity=".55" stroke-width="${n(Math.max(sw * .6, .25))}"`;
            const i = Math.min(w, h) * .14;
            let g = '';

            switch (p.type) {
                case 'panel':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="1" ${st}/>` +
                        `<rect x="${n(x + i)}" y="${n(y + i)}" width="${n(Math.max(w - 2 * i, 0))}" height="${n(Math.max(h - 2 * i, 0))}" rx=".6" ${ln}/>`;
                    break;
                case 'arch':
                    g = `<path d="${this.arch(x, y, w, h)}" ${st}/>` +
                        `<path d="${this.arch(n(x + i), n(y + i), n(Math.max(w - 2 * i, 0)), n(Math.max(h - 2 * i, 0)))}" ${ln}/>`;
                    break;
                case 'glass':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" ${st}/>` +
                        this.muntins(x, y, w, h, cnt(p.cols), cnt(p.rows), stroke, sw) +
                        `<path d="M${n(x + w * .15)} ${n(y + h * .3)}L${n(x + w * .45)} ${n(y + h * .05)}" stroke="#fff" stroke-opacity=".6" stroke-width="1.2" stroke-linecap="round"/>`;
                    break;
                case 'round': {
                    const cid = 'dk-r-' + sid(p.id);
                    g = `<clipPath id="${cid}"><ellipse cx="${n(x + w / 2)}" cy="${n(y + h / 2)}" rx="${n(w / 2)}" ry="${n(h / 2)}"/></clipPath>` +
                        `<ellipse cx="${n(x + w / 2)}" cy="${n(y + h / 2)}" rx="${n(w / 2)}" ry="${n(h / 2)}" ${st}/>` +
                        `<g clip-path="url(#${cid})">${this.muntins(x, y, w, h, cnt(p.cols), cnt(p.rows), stroke, sw)}</g>`;
                    break;
                }
                case 'handle':
                    g = `<rect x="${n(x - w * 2.2)}" y="${n(y + h / 2 - w * .22)}" width="${n(w * 2.6)}" height="${n(w * .44)}" rx="${n(w * .22)}" ${st}/>` +
                        `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${n(w / 2)}" ${st}/>` +
                        `<circle cx="${n(x + w / 2)}" cy="${n(y + h / 2)}" r="${n(w * .3)}" fill="#000" fill-opacity=".25"/>`;
                    break;
                case 'hinge':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx=".4" ${st}/>` +
                        `<path d="M${x} ${n(y + h / 2)}H${n(x + w)}" stroke="${stroke}" stroke-width=".3"/>`;
                    break;
                case 'peephole':
                    g = `<ellipse cx="${n(x + w / 2)}" cy="${n(y + h / 2)}" rx="${n(w / 2)}" ry="${n(h / 2)}" ${st}/>` +
                        `<ellipse cx="${n(x + w / 2)}" cy="${n(y + h / 2)}" rx="${n(w * .22)}" ry="${n(h * .22)}" fill="#111" />`;
                    break;
                case 'slot':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx=".8" ${st}/>` +
                        `<rect x="${n(x + 1.5)}" y="${n(y + h * .32)}" width="${n(Math.max(w - 3, 0))}" height="${n(h * .36)}" rx=".4" fill="#111"/>`;
                    break;
                case 'plate': {
                    const o = Math.min(2.2, w / 3, h / 3);
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx=".8" ${st}/>` +
                        [[x + o, y + o], [x + w - o, y + o], [x + o, y + h - o], [x + w - o, y + h - o]]
                            .map(([a, b]) => `<circle cx="${n(a)}" cy="${n(b)}" r=".7" fill="#000" fill-opacity=".35"/>`).join('');
                    break;
                }
                case 'bar':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${n(w / 2)}" ${st}/>` +
                        `<path d="M${n(x + w * .3)} ${n(y + h * .05)}V${n(y + h * .95)}" stroke="#fff" stroke-opacity=".55" stroke-width="${n(Math.max(w * .15, .3))}" stroke-linecap="round"/>` +
                        [.08, .92].map(k => `<circle cx="${n(x + w / 2)}" cy="${n(y + h * k)}" r="${n(w * .28)}" fill="#000" fill-opacity=".3"/>`).join('');
                    break;
                case 'rail':
                    g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" ${st}/>` +
                        `<path d="M${x} ${n(y + h * .3)}H${n(x + w)}" stroke="#fff" stroke-opacity=".5" stroke-width=".4"/>` +
                        `<path d="M${x} ${n(y + h * .75)}H${n(x + w)}" stroke="#000" stroke-opacity=".25" stroke-width=".4"/>`;
                    break;
                case 'draw': {
                    const d = this.pathD(p.pts || []);
                    const tf = `translate(${n(p.x)} ${n(p.y)})`;
                    g = `<path d="${d}" transform="${tf}" fill="none" stroke="transparent" stroke-width="${n(Math.max(sw, 4))}" stroke-linecap="round"/>` +
                        `<path d="${d}" transform="${tf}" fill="none" stroke="${stroke}" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round"/>`;
                    break;
                }
                default:
                    return '';
            }
            return `<g data-id="${sid(p.id)}" style="cursor:move">${g}</g>`;
        },

        selection() {
            const p = this.sel; if (!p) return '';
            const b = this.bbox(p), hs = (this.H + 50) / 60;
            let s = `<rect x="${n(b.x)}" y="${n(b.y)}" width="${n(b.w)}" height="${n(b.h)}" fill="none" stroke="#2563eb" stroke-width=".5" stroke-dasharray="2 1.5" pointer-events="none"/>`;
            if (p.type !== 'draw') {
                const pts = [
                    ['nw', b.x, b.y], ['n', b.x + b.w / 2, b.y], ['ne', b.x + b.w, b.y],
                    ['e', b.x + b.w, b.y + b.h / 2], ['se', b.x + b.w, b.y + b.h],
                    ['s', b.x + b.w / 2, b.y + b.h], ['sw', b.x, b.y + b.h], ['w', b.x, b.y + b.h / 2],
                ];
                for (const [d, cx, cy] of pts) {
                    s += `<rect data-h="${d}" x="${n(cx - hs / 2)}" y="${n(cy - hs / 2)}" width="${n(hs)}" height="${n(hs)}" fill="#fff" stroke="#2563eb" stroke-width=".5" style="cursor:${d}-resize"/>`;
                }
            }
            return s;
        },

        dimsSvg() {
            const W = this.W, H = this.H, fr = 4;
            const t = 'font-family="ui-sans-serif,system-ui,sans-serif" font-size="4.5" fill="#6b7280" text-anchor="middle"';
            return `<g stroke="#6b7280" stroke-width=".4" fill="none" pointer-events="none">` +
                `<path d="M0 ${H + 10}H${W}M0 ${H + 7}v6M${W} ${H + 7}v6"/>` +
                `<path d="M${-fr - 10} 0V${H}M${-fr - 13} 0h6M${-fr - 13} ${H}h6"/></g>` +
                `<text x="${W / 2}" y="${H + 17}" ${t}>${W} cm</text>` +
                `<text transform="translate(${-fr - 14} ${H / 2}) rotate(-90)" ${t}>${H} cm</text>`;
        },

        scene(clean = false) {
            const W = this.W, H = this.H, fr = 4;
            const c = col(this.doc.color, '#8b5a2b'), fc = col(this.doc.frameColor, '#4a2f17');
            let s = `<defs>
                <pattern id="dk-brush" width="3" height="10" patternUnits="userSpaceOnUse"><path d="M.5 0V10M2 0V10" stroke="#fff" stroke-opacity=".14" stroke-width=".15"/></pattern>
                <linearGradient id="dk-metal" x1="0" x2="1"><stop offset="0" stop-color="#fff" stop-opacity=".25"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".2"/></linearGradient>
                <pattern id="dk-grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M10 0H0V10" fill="none" stroke="#7b8794" stroke-opacity=".3" stroke-width=".2"/></pattern>
                <clipPath id="dk-clip"><rect width="${W}" height="${H}"/></clipPath>
            </defs>`;

            if (!clean && this.grid) s += `<rect x="-25" y="-25" width="${W + 50}" height="${H + 50}" fill="url(#dk-grid)" pointer-events="none"/>`;
            s += `<rect x="${-fr}" y="${-fr}" width="${W + 2 * fr}" height="${H + fr}" fill="${fc}" stroke="#000" stroke-opacity=".5" stroke-width=".5"/>`;
            s += `<rect width="${W}" height="${H}" fill="${c}" stroke="#000" stroke-opacity=".6" stroke-width=".5"/>`;
            s += `<rect width="${W}" height="${H}" fill="url(#dk-brush)" pointer-events="none"/>`;
            s += `<rect width="${W}" height="${H}" fill="url(#dk-metal)" pointer-events="none"/>`;
            const pf = Math.min(W / 2 - 1, Math.min(15, Math.max(0, n(this.doc.profile))));
            if (pf > 0) {
                s += `<path d="M0 0H${W}V${H}H0Z M${pf} ${pf}V${H - pf}H${W - pf}V${pf}Z" fill="#fff" fill-opacity=".14" fill-rule="evenodd" pointer-events="none"/>`;
                s += `<rect x="${pf}" y="${pf}" width="${n(W - 2 * pf)}" height="${n(H - 2 * pf)}" fill="none" stroke="#000" stroke-opacity=".35" stroke-width=".5" pointer-events="none"/>`;
            }

            // Sobreluz fijo en la parte alta de la puerta
            if (this.doc.conSobreluz) {
                const hs = Math.min(H * .6, Math.max(5, n(this.doc.altoSobreluz)));
                s += `<rect width="${W}" height="${hs}" fill="#bfe3f2" fill-opacity=".6" pointer-events="none"/>` +
                     `<path d="M0 ${hs}H${W}" stroke="#222" stroke-width="1.2" pointer-events="none"/>` +
                     `<text x="${W / 2}" y="${n(hs / 2 + 1.5)}" font-family="ui-sans-serif,system-ui,sans-serif" font-size="4" fill="#334155" text-anchor="middle" pointer-events="none">SOBRELUZ · ${n(this.doc.altoSobreluz)} cm</text>`;
            }

            s += `<g clip-path="url(#dk-clip)">${this.doc.parts.map(p => this.shape(p)).join('')}</g>`;
            s += `<path d="M-25 ${H}H${W + 25}" stroke="#444" stroke-width="1" pointer-events="none"/>`;
            if (this.dims) s += this.dimsSvg();
            if (!clean) s += this.selection();
            return s;
        },

        // ---------- Exportar ----------
        svgFile() {
            const m = 25, vw = this.W + 2 * m, vh = this.H + 2 * m;
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${-m} ${-m} ${vw} ${vh}" width="${vw * 6}" height="${vh * 6}">` +
                `<rect x="${-m}" y="${-m}" width="${vw}" height="${vh}" fill="#ffffff"/>${this.scene(true)}</svg>`;
        },
        fileName() { return (this.$wire.name || 'puerta').replace(/[^\w-]+/g, '_') },
        download(name, blob) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = name; a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        },
        exportSvg() {
            this.download(this.fileName() + '.svg', new Blob([this.svgFile()], { type: 'image/svg+xml' }));
        },
        exportPng() {
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = img.width; c.height = img.height;
                c.getContext('2d').drawImage(img, 0, 0);
                c.toBlob(b => this.download(this.fileName() + '.png', b));
            };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.svgFile());
        },
    };
});
</script>
@endscript
