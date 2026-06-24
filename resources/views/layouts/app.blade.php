<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>MediPro - {{ $title ?? 'MediPro' }}</title>

    <script src="https://kit.fontawesome.com/a22afade38.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite('resources/css/app.css')

    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://unpkg.com/dom-to-image-more@3.2.0/dist/dom-to-image-more.min.js"></script>


    <script src="https://unpkg.com/dom-to-image-more@3.2.0/dist/dom-to-image-more.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* Fondo con movimiento de aurora */
        body {
            background: linear-gradient(125deg, #ffffff 0%, #f0f7ff 50%, #e0eaff 100%);
            overflow: hidden;
        }

        /* Animación de burbujas orgánicas */
        @keyframes orbit {
            0% {
                transform: translate(0, 0) scale(1) rotate(0deg);
            }

            33% {
                transform: translate(100px, 100px) scale(1.2) rotate(120deg);
            }

            66% {
                transform: translate(-50px, 150px) scale(0.8) rotate(240deg);
            }

            100% {
                transform: translate(0, 0) scale(1) rotate(360deg);
            }
        }

        /* Partículas pequeñas de brillo */
        @keyframes shine {

            0%,
            100% {
                opacity: 0.2;
                transform: translateY(0);
            }

            50% {
                opacity: 0.8;
                transform: translateY(-20px);
            }
        }

        .bubble {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            mix-blend-mode: multiply;
            animation: orbit 25s infinite linear;
            z-index: -1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(59, 130, 246, 0.5);
            border-radius: 50%;
            animation: shine 5s infinite ease-in-out;
        }

        /* Efecto de cristal para las tarjetas */
        .glass-card {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        /* Texto con gradiente fluido */
        .text-gradient {
            background: linear-gradient(to right, #1e40af, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: textFlow 5s linear infinite;
        }

        @keyframes textFlow {
            to {
                background-position: 200% center;
            }
        }

        /* Animación sutil para el badge superior */
        @keyframes pulse-soft {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }

        .badge-anim {
            animation: pulse-soft 3s infinite ease-in-out;
        }
    </style>
    <style>
        [wire\:cloak],
        [x-cloak] {
            display: none !important;
        }
    </style>

    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }

            #area-mapa-corte,
            #area-mapa-corte * {
                visibility: visible !important;
            }

            #area-mapa-corte {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                background: white !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .shadow-2xl,
            .shadow-inner,
            .shadow-xl {
                box-shadow: none !important;
            }

            button,
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="relative min-h-screen overflow-y-auto">

    <div x-data="{ loading: false }" x-on:livewire:navigate.window="loading = true"
        x-on:livewire:navigated.window="loading = false" x-show="loading" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/35 backdrop-blur-xs">

        <div class="flex flex-col">
            <div class=" inset-0 flex items-center justify-center">
                <img src="{{ asset('img/tape.gif') }}" alt="" srcset="" class="rounded-full w-40 h-40">
            </div>

        </div>
    </div>

    @if (!request()->is('/'))
        <a href="/" wire:navigate
            class="fixed z-[999] top-4 left-4 flex items-center justify-center w-12 h-12 bg-linear-to-br bg-orange-600 text-white rounded-2xl shadow-lg transition-colors floating-button border-2 border-white/20">
            <i class="fa-solid fa-house text-lg"></i>
        </a>
    @endif

    <div wire:loading.remove>
        {{ $slot }}
    </div>

    @livewireScripts

    <p class="text-[10px] z-['999'] text-end px-5 sticky bottom-0 tracking-[0.2em] uppercase text-gray-400">
        by <a href="https://www.facebook.com/share/1Eh3Dx3iKB/" target="_blank" rel="noopener noreferrer">
            <span class="font-bold text-blue-600">Jhon Rosales</span></a>
    </p>
</body>

</html>
