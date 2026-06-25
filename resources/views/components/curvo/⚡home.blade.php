<?php

use Livewire\Component;

new class extends Component {
    // Sin tipado float para evitar errores cuando el input queda vacío
    public $cuerda = 101;
    public $flecha = 13;
    public $altura = 143;

    public function getInfoProperty(): array
    {
        $c = (float) ($this->cuerda ?: 0);
        $f = (float) ($this->flecha ?: 0);

        if ($c <= 0 || $f <= 0) {
            return [
                'r' => '0.00',
                'angulo' => '0.00',
                'desarrollo' => '0.00',
            ];
        }

        $r = ($c * $c) / (8 * $f) + $f / 2;

        $ratio = min(1, $c / (2 * $r));
        $anguloRad = 2 * asin($ratio);

        return [
            'r' => number_format($r, 2, '.', ''),
            'angulo' => number_format(rad2deg($anguloRad), 2, '.', ''),
            'desarrollo' => number_format($r * $anguloRad, 2, '.', ''),
        ];
    }
};
?>

<div class="max-w-6xl mx-auto p-6 md:p-10 bg-white text-slate-800 rounded-3xl shadow-xl border border-slate-200">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 mb-8 border-b border-slate-200">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900">
                Plano 2D · Vidrio Curvo
            </h1>
        </div>

        <div class="flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-100 rounded-xl">
            <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
            <span class="text-xs font-semibold text-slate-600">
                calculo en tiempo real
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- PANEL DERECHO -->
        <div class="lg:col-span-5 flex flex-col gap-6">

            <!-- PARÁMETROS -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h3 class="font-bold text-slate-700 mb-5">
                    Parámetros
                </h3>

                <div class="space-y-4">

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">
                            Cuerda (cm)
                        </label>

                        <input type="number" step="0.01" min="0" wire:model.lazy="cuerda"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">
                            Flecha (cm)
                        </label>

                        <input type="number" step="0.01" min="0" wire:model.lazy="flecha"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">
                            Altura (cm)
                        </label>

                        <input type="number" step="0.01" min="0" wire:model.lazy="altura"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                    </div>

                </div>
            </div>

            <!-- RESULTADOS -->
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 shadow-sm">

                <h3 class="font-bold text-blue-700 mb-5">
                    Resultados
                </h3>

                <div class="space-y-3 text-sm">

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">
                            Desarrollo (arco)
                        </span>

                        <span class="font-bold text-slate-900">
                            {{ $this->info['desarrollo'] }} cm
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">
                            Radio
                        </span>

                        <span class="font-bold text-slate-900">
                            {{ $this->info['r'] }} cm
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">
                            Ángulo
                        </span>

                        <span class="font-bold text-slate-900">
                            {{ $this->info['angulo'] }}°
                        </span>
                    </div>

                    <div class="border-t border-blue-200 pt-3 mt-3 flex items-center justify-between">
                        <span class="text-slate-600">
                            Altura
                        </span>

                        <span class="font-bold text-slate-900">
                            {{ $altura }} cm
                        </span>
                    </div>

                </div>

            </div>

        </div>
        <!-- IMAGEN ESTÁTICA -->
        <div class="lg:col-span-7">
            <div
                class="bg-slate-50 border border-slate-200 rounded-2xl p-8 shadow-sm h-full flex items-center justify-center">

                <img src="{{ asset('img/curvo.png') }}" alt="Vidrio Curvo"
                    class="max-w-full max-h-[500px] object-contain">

            </div>
        </div>



    </div>

</div>
