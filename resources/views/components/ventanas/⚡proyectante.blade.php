<?php

use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    public array $ventanas = [];
    public int $ventanaActiva = 0;

    public array $data = [];

    public $ancho = 90;
    public $alto = 120;

    public function mount()
    {
        $this->procesarPerfiles();
        $this->descuentos();

        $this->ventanas = session('proyectantes', [
            [
                'nombre' => 'V - 1',
                'ancho' => 90,
                'alto' => 120,
            ],
        ]);

        $this->ventanaActiva = 0;

        $this->cargarVentanaActiva();
    }

    public function procesarPerfiles(): void
    {
        $ruta = public_path('datos.xlsx');

        if (!file_exists($ruta)) {
            return;
        }

        $this->data = collect(Excel::toArray([], $ruta)[0])
            ->pluck(0)
            ->filter()
            ->values()
            ->toArray();
    }

    public function confirmarImpresion(): void
    {
        session()->forget('datos_lote');
        session()->forget('proyectantes');

        $this->ventanas = [
            [
                'nombre' => 'V - 1',
                'ancho' => 90,
                'alto' => 120,
            ],
        ];

        $this->ventanaActiva = 0;

        $this->cargarVentanaActiva();
    }

    public function eliminarVentana(int $index): void
    {
        if (count($this->ventanas) <= 1) {
            return;
        }

        unset($this->ventanas[$index]);

        $this->ventanas = array_values($this->ventanas);

        if ($this->ventanaActiva >= count($this->ventanas)) {
            $this->ventanaActiva = count($this->ventanas) - 1;
        }

        $this->cargarVentanaActiva();

        session()->put('proyectantes', $this->ventanas);

        $this->dispatch('delete', 'Vista eliminada con éxito');
    }

    private function cargarVentanaActiva(): void
    {
        $v = $this->ventanas[$this->ventanaActiva];

        $this->ancho = $v['ancho'] ?? 90;
        $this->alto = $v['alto'] ?? 120;
    }

    public function cambiarVentana(int $i): void
    {
        $this->guardarVentanaActual();

        $this->ventanaActiva = $i;

        $this->cargarVentanaActiva();
    }

    public function agregarVentana(): void
    {
        $this->guardarVentanaActual();

        $this->ventanas[] = [
            'nombre' => 'V - ' . (count($this->ventanas) + 1),
            'ancho' => $this->ancho,
            'alto' => $this->alto,
        ];

        $this->ventanaActiva = count($this->ventanas) - 1;

        session()->put('proyectantes', $this->ventanas);

        $this->dispatch('correcto', 'Vista agregada con éxito');
    }

    public function updated($propertyName)
    {
        $this->guardarVentanaActual();
        $this->descuentos();

        session()->put('proyectantes', $this->ventanas);
    }

    public $datos = [];

    public function descuentos()
    {
        $descuentoMarco = 0.3;
        $descuentoNave = 2;

        $ancho = (float) $this->ancho;
        $alto = (float) $this->alto;

        $medidaMarcoAlto = $alto - $descuentoMarco;
        $medidaMarcoAncho = $ancho - $descuentoMarco;

        $medidaNaveAlto = $alto - $descuentoNave;
        $medidaNaveAncho = $ancho - $descuentoNave;

        $this->datos = [
            [
                'perfil' => '172',
                'tipo' => 'Marco',
                'altos' => [$medidaMarcoAlto, $medidaMarcoAlto],
                'anchos' => [$medidaMarcoAncho, $medidaMarcoAncho],
                'cantidad' => 2,
            ],
            [
                'perfil' => '416',
                'tipo' => 'Nave',
                'altos' => [$medidaNaveAlto, $medidaNaveAlto],
                'anchos' => [$medidaNaveAncho, $medidaNaveAncho],
                'cantidad' => 2,
            ],
        ];
    }

    private function snapshotVentana(): array
    {
        return [
            'ancho' => $this->ancho,
            'alto' => $this->alto,
        ];
    }

    private function guardarVentanaActual(): void
    {
        if (!isset($this->ventanas[$this->ventanaActiva])) {
            return;
        }

        $this->ventanas[$this->ventanaActiva] = [
            'nombre' => $this->ventanas[$this->ventanaActiva]['nombre'],
            'ancho' => $this->ancho,
            'alto' => $this->alto,
        ];
    }
};
?>

<script src="https://cdn.tailwindcss.com"></script>

<div x-data="{ open: false }" class="min-h-screen flex flex-col items-center justify-center p-2 overflow-hidden">

    <p class="text-2xl text-slate-500 font-extrabold tracking-widest mb-6">
        {{ $ventanas[$ventanaActiva]['nombre'] ?? '—' }}
    </p>
    <div
        class="lg:flex w-full grid grid-cols-1 lg:gap-2 justify-between items-center gap-1 mb-6 px-2 overflow-x-auto border-b border-gray-200">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($ventanas as $index => $v)
                <div wire:key="ventana-{{ $index }}"
                    class="group relative flex items-center rounded-t-xl border transition-all {{ $ventanaActiva == $index ? 'bg-white border-gray-200 text-blue-600 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]' : 'bg-gray-100 border-transparent text-gray-400 hover:bg-gray-200' }}">
                    <button wire:click="cambiarVentana({{ $index }})"
                        class="flex items-center gap-2 px-5 py-2 text-xs font-black uppercase tracking-tighter focus:outline-none">
                        <i class="fa-solid fa-window-maximize"></i> {{ $v['nombre'] }} </button> <button
                        wire:click.stop="eliminarVentana({{ $index }})"
                        class="absolute -right-1 -top-1 w-5 h-5 rounded-full bg-red-500 text-white text-[10px] opacity-0 group-hover:opacity-100 transition hover:bg-red-600 flex items-center justify-center shadow">
                        ✕ </button>
                </div>
            @endforeach
            <button wire:click="agregarVentana"
                class="ml-2 px-4 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors flex items-center gap-2 text-xs font-bold">
                <i class="fa-solid fa-plus-circle"></i> Nuevo
            </button>
            <button wire:click="confirmarImpresion"
                class="ml-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors flex items-center gap-2 text-xs font-bold">
                <i class="fa-solid fa-trash"></i>
                Vaciar
            </button>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2 lg:gap-6 p-2 mb-6 md:grid-cols-3 lg:grid-cols-5">
        <div class="relative group"> <label
                class="block mb-2 ml-1 text-xs font-bold tracking-wider text-gray-500 uppercase"> Ancho <span
                    class="text-blue-500">(cm)</span> </label> <input type="number" min="1"
                wire:model.lazy="ancho"
                class="w-full px-4 py-3 font-bold text-gray-700 border-2 border-gray-200 rounded-2xl focus:border-blue-500 outline-none">
        </div>
        <div class="relative group"> <label
                class="block mb-2 ml-1 text-xs font-bold tracking-wider text-gray-500 uppercase"> Alto <span
                    class="text-blue-500">(cm)</span> </label> <input type="number" min="1"
                wire:model.lazy="alto"
                class="w-full px-4 py-3 font-bold text-gray-700 border-2 border-gray-200 rounded-2xl focus:border-blue-500 outline-none">
        </div>
    </div>

    <div class="mb-16 text-center">
        <h1 class="text-xs tracking-[0.45em] uppercase text-neutral-700 font-bold">
            Plano 2d: Proyectante
        </h1>
    </div>

    <div class="flex w-full flex-wrap justify-center items-start gap-2">

        {{-- plano --}}

        <div class="flex flex-wrap justify-center items-end gap-5">
            <div class="flex flex-col items-center">

                <div @click="open=!open" class="relative w-72 h-92 cursor-pointer">

                    <div class="absolute inset-0 border-[16px] border-gray-900 shadow-lg">

                        <div class="absolute inset-0 bg-gray-900 border border-neutral-300 origin-top transition-all duration-700 ease-[cubic-bezier(.22,1,.36,1)] shadow-2xl"
                            :style="open ? 'transform:perspective(1600px) rotateX(-30deg) translateY(22px);' :
                                'transform:perspective(1600px) rotateX(0deg) translateY(0px);'">

                            <div
                                class="absolute text-center flex justify-center items-center inset-4 bg-sky-100 border border-sky-100">
                                <p>vidrio</p>
                            </div>

                            <div class="absolute bottom-3 left-1/2 transition-all duration-500 ease-out"
                                :style="open ? 'transform:translateX(-50%) rotate(90deg);' :
                                    'transform:translateX(-50%) rotate(0deg);'">
                                <div class="w-12 h-2.5 bg-black rounded-full shadow-lg"></div>
                            </div>

                        </div>

                    </div>

                    <div class="absolute -bottom-8 lg:-bottom-12 left-0 w-full flex justify-center h-4 items-center">
                        <div class="w-[90%] h-[2px] relative bg-blue-500 flex items-center justify-between">

                            <div
                                class="absolute left-0 -translate-x-full w-0 h-0 border-y-[5px] border-y-transparent border-r-[8px] border-r-blue-500">
                            </div>

                            <div class="absolute inset-0 flex items-center justify-center">
                                <div
                                    class="bg-white px-3 py-0.5 lg:text-[14px]  text-[10px] font-bold text-blue-600 border border-blue-200 rounded shadow-sm z-10 whitespace-nowrap">
                                    {{ $ancho ?? 90 }} cm
                                </div>
                            </div>

                            <div
                                class="absolute right-0 translate-x-full w-0 h-0 border-y-[5px] border-y-transparent border-l-[8px] border-l-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="absolute top-0 flex items-center justify-center -left-8 h-full lg:-left-14">
                        <div class="w-[2px] h-[90%] relative bg-blue-500 flex flex-col items-center justify-between">

                            <div
                                class="absolute top-0 -translate-y-full w-0 h-0 border-x-[5px] border-x-transparent border-b-[8px] border-b-blue-500">
                            </div>

                            <div class="absolute inset-0 flex items-center justify-center">
                                <div
                                    class="transform rotate-45 bg-white px-3 py-0.5 lg:text-[14px] text-[10px] font-bold text-blue-600 border border-blue-200 rounded shadow-sm z-10 whitespace-nowrap">
                                    {{ $alto ?? 55 }} cm
                                </div>
                            </div>

                            <div
                                class="absolute bottom-0 translate-y-full w-0 h-0 border-x-[5px] border-x-transparent border-t-[8px] border-t-blue-500">
                            </div>
                        </div>
                    </div>

                </div>

                <span class="mt-16 text-[10px] tracking-[0.25em] uppercase text-neutral-400 font-bold">
                    Frontal
                </span>

            </div>

            <div class="flex flex-col items-center">

                <div class="relative w-40 h-92 overflow-visible">

                    <div class="absolute left-0 top-0 w-5 h-full bg-gray-900"></div>

                    <div class="absolute left-[20px] w-3 h-[367px] origin-top-left transition-all duration-700 ease-[cubic-bezier(.22,1,.36,1)] z-30"
                        :style="open ? 'transform:rotate(-28deg) translateY(8px);' : 'transform:rotate(0deg) translateY(0px);'">

                        <div class="absolute inset-0 bg-gray-900 border z-[999px] border-gray-900 shadow-xl"></div>

                        <div class="absolute top-[46px] left-0 origin-left z-10 transition-all duration-[800ms] ease-[cubic-bezier(.22,1,.36,1)]"
                            :style="open ? 'width:40px; transform:rotate(160deg);' : 'width:0px; transform:rotate(180deg);'">
                            <div class="h-[5px] bg-gray-400 w-full rounded-full"></div>
                        </div>

                        <div class="absolute top-[146px] left-0 origin-left z-10 transition-all duration-[815ms] ease-[cubic-bezier(.22,1,.36,1)]"
                            :style="open ? 'width:112px; transform:rotate(160deg);' : 'width:0px; transform:rotate(180deg);'">
                            <div class="h-[5px] bg-gray-400 w-full rounded-full"></div>
                        </div>

                        <div class="absolute bottom-6 -right-2 transition-all duration-500 ease-out"
                            :style="open ? 'transform:rotate(90deg);' : 'transform:rotate(0deg);'">
                            <div class="w-4 h-2 bg-black rounded-sm"></div>
                        </div>

                    </div>

                </div>

                <span class="mt-16 text-[10px] tracking-[0.25em] uppercase text-neutral-400 font-bold">
                    Lateral
                </span>

            </div>
        </div>

        <div class="lg:w-[50%] w-full rounded-xl border border-slate-200 shadow-sm bg-white p-4">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-2.5 h-5 bg-blue-600 rounded-full inline-block"></span>
                    Requerimientos / Perfiles
                </h3>
            </div>

            <table class="w-full text-left border-collapse">
                <thead>
                    <tr
                        class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                        <th class="p-3.5">COD / Nombre</th>
                        <th class="p-3.5 text-center">Medidas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">

                    @foreach ($this->datos as $item)
                        @php
                            $codigoBuscado = $item['perfil'];
                            $nombreProductoExcel = 'Perfil no identificado';

                            foreach ($this->data as $nombreCompleto) {
                                if (str_contains((string) $nombreCompleto, (string) $codigoBuscado)) {
                                    $nombreProductoExcel = $nombreCompleto;
                                    break;
                                }
                            }

                            if (isset($item['tipo'])) {
                                $nombreProductoExcel .= ' - ' . $item['tipo'];
                            }
                        @endphp

                        <tr class="hover:bg-slate-50/80 border-b border-slate-100 transition-colors">
                            <td class="p-3.5 align-middle">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-2.5 py-1 text-xs font-bold rounded-md bg-blue-600 text-white shadow-sm font-mono tracking-wide">
                                        {{ $codigoBuscado }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 text-[11px] font-semibold text-slate-500 bg-slate-100 rounded border border-slate-200 uppercase">
                                        {{ $item['tipo'] }}
                                    </span>
                                </div>
                            </td>

                            <td class="p-3.5 align-middle">
                                <div class="flex flex-col gap-1.5  mx-auto">
                                    <div
                                        class="flex items-center justify-between bg-slate-50/50 px-2.5 py-1 rounded-lg border border-slate-200/60 shadow-sm">
                                        <div
                                            class="flex items-center gap-1.5 font-mono font-bold text-blue-700 text-[13px]">
                                            <span
                                                class="text-[10px] uppercase tracking-wider font-sans font-medium text-slate-400">H:</span>
                                            <span>{{ $item['altos'][0] }} cm</span>
                                        </div>
                                        <span
                                            class="bg-blue-50 text-blue-700 text-[11px] font-extrabold px-2 py-0.5 rounded-md border border-blue-200 min-w-[28px] text-center">
                                            = {{ count($item['altos']) }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-center justify-between bg-slate-50/50 px-2.5 py-1 rounded-lg border border-slate-200/60 shadow-sm">
                                        <div
                                            class="flex items-center gap-1.5 font-mono font-bold text-blue-700 text-[13px]">
                                            <span
                                                class="text-[10px] uppercase tracking-wider font-sans font-medium text-slate-400">W:</span>
                                            <span>{{ $item['anchos'][0] }} cm</span>
                                        </div>
                                        <span
                                            class="bg-blue-50 text-blue-700 text-[11px] font-extrabold px-2 py-0.5 rounded-md border border-blue-200 min-w-[28px] text-center">
                                            = {{ count($item['anchos']) }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                </tbody>


            </table>
            <p
                class="inline-flex w-full items-center gap-1.5 bg-amber-50 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-md border border-amber-200 shadow-sm w-fit select-none">
                <span
                    class="flex items-center justify-center w-4 h-4 bg-amber-200 rounded text-amber-900 text-[10px] font-black rotate-45 transform origin-center mr-0.5">∠</span>
                Cortes a 45° requeridos
            </p>
        </div>

    </div>

    <div class="mt-16 flex flex-col items-center gap-4">

        <button @click="open=!open"
            class="px-10 py-3 border-2 border-black bg-white text-[10px] tracking-[0.35em] uppercase font-black hover:bg-black hover:text-white transition-all duration-300 active:scale-95">
            <span x-text="open ? 'Cerrar' : 'Abrir'"></span>
        </button>

    </div>

</div>
