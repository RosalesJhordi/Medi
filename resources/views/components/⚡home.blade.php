<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Inicio')] class extends Component {};
?>

<div class=" h-auto flex items-center justify-center text-gray-800">

    <div class="absolute inset-0 overflow-auto -z-10">
        <div class="bubble w-[500px] h-[500px] bg-blue-300/40 top-[-10%] left-[-10%]"></div>
        <div class="bubble w-[400px] h-[400px] bg-purple-300/30 bottom-[5%] right-[-5%]"
            style="animation-duration: 30s; animation-delay: -5s;"></div>
        <div class="bubble w-[300px] h-[300px] bg-pink-200/40 top-[20%] right-[15%]"
            style="animation-duration: 20s; animation-delay: -2s;"></div>
        <div class="bubble w-[450px] h-[450px] bg-emerald-100/50 bottom-[-10%] left-[10%]"
            style="animation-duration: 35s;"></div>

        <div class="particle top-1/4 left-1/4"></div>
        <div class="particle top-1/3 right-1/4" style="animation-delay: 1s;"></div>
        <div class="particle bottom-1/4 left-1/2" style="animation-delay: 2s;"></div>
    </div>

    <div class="relative w-full lg:max-w-xl px-4 lg:px-6 text-center">

        <div class="flex justify-center mb-6 p-2 mt-2">

            <div
                class="inline-flex items-center gap-2 px-4 py-1 rounded-full
        bg-white/70 backdrop-blur-md border border-blue-400/40
        shadow-[0_0_20px_rgba(59,130,246,0.35)] animate-pulse">

                <!-- punto neon -->
                <span class="relative flex w-2 h-2">

                    <span class="absolute inline-flex w-full h-full bg-blue-500 rounded-full opacity-70 animate-ping">
                    </span>

                    <span
                        class="relative inline-flex w-2 h-2 bg-blue-400 rounded-full shadow-[0_0_10px_rgba(59,130,246,0.9)]">
                    </span>

                </span>

                <!-- texto neon -->
                <span
                    class="text-[10px] font-black uppercase tracking-[0.25em]
            text-blue-600 drop-shadow-[0_0_8px_rgba(59,130,246,0.6)]">
                    MediPro v1.0
                </span>

            </div>

        </div>

        <div class="flex justify-center mb-2">
            <span class="inline-block w-12 h-1 bg-blue-500 rounded-full opacity-50"></span>
        </div>

        <h1 class="pb-2 text-5xl font-black tracking-tighter lg:text-7xl text-gradient">
            Bienvenido
        </h1>

        <p class="mt-4 text-md lg:text-xl md:text-2xl font-light text-gray-500 tracking-wide uppercase text-[0.9rem]">
            ¿Qué desea realizar <span class="font-bold text-gray-700">hoy</span>?
        </p>

        <div class="grid grid-cols-2 lg:grid-cols-3 items-center justify-center w-full gap-6 p-2 lg:p-4 mt-6">

            <a href="{{ route('ventanas') }}" wire:navigate
                class="card flex flex-col items-center justify-center w-full lg:w-1/2 h-40 lg:w-40 lg:h-40 p-1.5 lg:p-4 space-y-2 lg:space-y-4 transition-transform cursor-pointer glass-card rounded-3xl hover:scale-105 active:scale-95">
                <span
                    class="absolute -top-2 -right-2
                 px-2.5 py-1
                  font-bold uppercase tracking-wide
                 bg-green-500 text-white
                 rounded-full shadow-md text-[8px]">
                    Disponible
                </span>
                <div class="icon font-extrabold text-6xl drop-shadow-[0_0_20px_#00F5FF]">
                    🪟
                </div>
                <span class="text-sm font-bold text-gray-700">Ventana</span>
            </a>

            <a href="{{ route('puertas') }}" wire:navigate
                class="flex flex-col items-center justify-center w-full lg:w-1/2 h-40 lg:w-40 lg:h-40 p-1.5 lg:p-4 space-y-2 lg:space-y-4 transition-transform cursor-pointer glass-card rounded-3xl hover:scale-105 active:scale-95">
                <span
                    class="absolute -top-2 -right-2
                 px-2.5 py-1
                  font-bold uppercase tracking-wide
                 bg-green-500 text-white
                 rounded-full shadow-md text-[8px]">
                    Disponible
                </span>
                <div class="icon font-extrabold text-6xl drop-shadow-[0_0_20px_#FF0044]">
                    🚪
                </div>
                <span class="text-sm font-bold text-gray-700">Puerta</span>
            </a>

            <a wire:navigate href="{{ route('mamparas') }}"
                class="relative flex cursor-pointer flex-col items-center justify-center
          w-full lg:w-1/2 h-40 lg:w-40 lg:h-40
          p-2 lg:p-4 space-y-3
          transition-transform
          glass-card rounded-3xl
          hover:scale-105 active:scale-95">

                {{-- BADGE flotante --}}
                <span
                    class="absolute -top-2 -right-2
                 px-2.5 py-1
                  font-bold uppercase tracking-wide
                 bg-green-500 text-white
                 rounded-full shadow-md text-[8px]">
                    Disponible
                </span>

                <div class="icon font-extrabold text-7xl drop-shadow-[0_0_20px_#FFD700]">
                    ◫
                </div>

                {{-- TEXTO --}}
                <span class="text-sm font-semibold text-gray-700">
                    Mampara
                </span>

            </a>

            <a wire:navigate href="{{ route('curvo') }}"
                class="relative flex  flex-col items-center justify-center
          w-full lg:w-1/2 h-40 lg:w-40 lg:h-40
          p-2 lg:p-4 space-y-3
          transition-transform
          glass-card rounded-3xl
          hover:scale-105 active:scale-95">

                {{-- BADGE flotante --}}
                <span
                    class="absolute -top-2 -right-2
                 px-2.5 py-1
                  font-bold uppercase tracking-wide
                 bg-blue-500 text-white
                 rounded-full shadow-md text-[8px]">
                    nuevo
                </span>

                <div class="icon text-6xl drop-shadow-[0_0_20px_#FFD700]">
                    📏
                </div>

                {{-- TEXTO --}}
                <span class="text-sm font-semibold text-gray-700">
                    Curvo
                </span>

            </a>


        </div>

        <div class="mt-20 opacity-60">
            <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-gray-300 to-transparent mb-4"></div>
            <p class="text-[10px] tracking-[0.2em] uppercase text-gray-400">
                by <a href="https://www.facebook.com/share/1Eh3Dx3iKB/" target="_blank" rel="noopener noreferrer">
                    <span class="font-bold text-blue-600">Jhon Rosales</span></a>
            </p>
        </div>
    </div>

</div>
