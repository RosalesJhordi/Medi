<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Mamparas')] class extends Component {
    public $mamparaSeleccionada = null;

    public function mount()
    {
        $this->mamparaSeleccionada = session('mamparaSeleccionada');
    }

    public function seleccionar($sistema)
    {
        $this->mamparaSeleccionada = $sistema;

        session([
            'mamparaSeleccionada' => $sistema,
        ]);
    }

    public function resetear()
    {
        $this->mamparaSeleccionada = null;

        session()->forget('mamparaSeleccionada');
    }
};
?>

<div class="min-h-screen bg-[#f1f5f9] p-6 md:p-12 relative font-sans antialiased">

    <div class="max-w-7xl mx-auto text-center mt-6 md:mt-0">
        {{-- HEADER --}}
        <header class="mb-12 transition-all duration-500">
            <h1 class="text-3xl md:text-5xl font-black text-[#2e3e4e] tracking-widest uppercase border-b-4 border-gray-300 inline-block pb-2 px-4">
                @if (!$mamparaSeleccionada)
                    MAMPARAS
                @else
                    {{ $mamparaSeleccionada }}
                @endif
            </h1>

            @if (!$mamparaSeleccionada)
                <p class="text-gray-500 mt-4 text-sm md:text-base font-medium tracking-wide">
                    Seleccione el tipo de sistema de mampara
                </p>
            @endif
        </header>

        {{-- GRID DE SISTEMAS DE MAMPARAS --}}
        @if (!$mamparaSeleccionada)
            @php
                // Configuración orientada 100% a sistemas de mamparas convencionales y de alta gama
                $mamparas = [
                    [
                        'id' => 'Sistema Corredizo',
                        'icon' => 'fa-arrows-left-right',
                        'desc' => 'Hojas deslizantes con rodamientos tandem de alta capacidad.',
                        'bgIcon' => 'bg-indigo-50 text-indigo-600',
                        'btnClass' => 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/20',
                    ],
                    [
                        'id' => 'Sistema Batiente / Pivot',
                        'icon' => 'fa-door-open',
                        'desc' => 'Apertura pivotante o batiente con frenos hidráulicos de piso.',
                        'bgIcon' => 'bg-emerald-50 text-emerald-600',
                        'btnClass' => 'bg-emerald-500 hover:bg-emerald-600 shadow-emerald-500/20',
                    ],
                    [
                        'id' => 'Paño Fijo / Templado',
                        'icon' => 'fa-border-all',
                        'desc' => 'División fija minimalista con perfiles en U o conectores espiga.',
                        'bgIcon' => 'bg-pink-50 text-pink-600',
                        'btnClass' => 'bg-pink-500 hover:bg-pink-600 shadow-pink-500/20',
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto px-4 mt-8">
                @foreach ($mamparas as $s)
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 shadow-[0_10px_25px_rgba(0,0,0,0.02)] transition-all duration-300 hover:shadow-[0_15px_35px_rgba(0,0,0,0.05)] hover:-translate-y-1 text-left flex flex-col justify-between min-h-[250px]">
                        
                        <div>
                            {{-- Icono representativo --}}
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-6 {{ $s['bgIcon'] }}">
                                <i class="fa-solid {{ $s['icon'] }} text-xl"></i>
                            </div>

                            {{-- Información técnica --}}
                            <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight mb-2">
                                {{ $s['id'] }}
                            </h2>
                            <p class="text-gray-400 text-sm font-medium leading-relaxed mb-6">
                                {{ $s['desc'] }}
                            </p>
                        </div>

                        {{-- Botón abrir sistema --}}
                        <div>
                            <button wire:click="seleccionar('{{ $s['id'] }}')" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white rounded-xl shadow-lg transition-all duration-300 transform active:scale-95 group {{ $s['btnClass'] }}">
                                <span>Abrir sistema</span>
                                <i class="fa-solid fa-arrow-right text-[10px] transition-transform group-hover:translate-x-1"></i>
                            </button>
                        </div>

                    </div>
                @endforeach
            </div>

        {{-- INYECCIÓN DINÁMICA DE VISTAS SEGÚN EL SISTEMA --}}
        @elseif ($mamparaSeleccionada === 'Sistema Corredizo')
            <livewire:mamparas.sistema-corredizo />
        @elseif ($mamparaSeleccionada === 'Sistema Batiente / Pivot')
            <livewire:mamparas.sistema-batiente />
        @elseif ($mamparaSeleccionada === 'Paño Fijo / Templado')
            <livewire:mamparas.panio-fijo />
        @else
            {{-- Módulo de contingencia por si se añade un sistema no registrado --}}
            <div class="py-12 flex items-center justify-center px-4">
                <div class="relative max-w-md w-full bg-white rounded-3xl shadow-sm border border-gray-100 p-10 text-center">
                    <div class="mb-6">
                        <i class="fa-solid fa-screwdriver-wrench text-5xl text-gray-300"></i>
                    </div>
                    <h1 class="text-2xl font-black text-gray-800 mb-2">Módulo en Diseño</h1>
                    <p class="text-gray-400 text-sm mb-6">Cargando las librerías de perfiles y accesorios para este tipo de mampara.</p>
                    <button wire:click="resetear" class="px-5 py-2 bg-gray-900 text-white font-bold text-xs rounded-xl hover:bg-gray-800 uppercase tracking-wider">
                        Regresar
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>