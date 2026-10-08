@extends('layouts.admin')

@section('title', 'Ayuda')

{{--
    Menú lateral › Ayuda: un buscador, los primeros pasos, un tema por sección y las
    preguntas frecuentes. Cada tema es la misma ayuda que "Cómo funciona" en su
    pantalla (ver App\Support\TemasDeAyuda).
--}}
@section('content')
@php
    $principales = array_diff_key($temas, array_flip(['primeros-pasos', 'preguntas-frecuentes']));
@endphp

<div class="mb-4">
    <h3 class="mb-1"><i class="bi bi-question-circle"></i> Ayuda</h3>
    <p class="text-muted mb-3">Todo lo que hace falta para manejar tu tienda, explicado fácil.</p>
    <div class="position-relative" style="max-width: 560px;">
        <i class="bi bi-search position-absolute text-muted" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
        <input type="search" class="form-control form-control-lg ps-5" id="buscar-ayuda" placeholder="¿Qué querés hacer? Ej.: talles, precios, WhatsApp" autocomplete="off" aria-label="Buscar en la ayuda">
    </div>
</div>

<div id="ayuda-sin-resultados" class="alert alert-light border d-none">
    <i class="bi bi-emoji-neutral"></i> No encontramos nada con esa palabra. Probá con otra, por ejemplo «stock» o «categoría».
</div>

@isset($temas['primeros-pasos'])
    <div class="card mb-4 ayuda-tema" data-buscar="{{ $temas['primeros-pasos']['buscar'] }}">
        <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-flag text-primary"></i> <strong>Empezá por acá</strong></div>
        <div class="card-body pb-1">
            @include('admin.ayudas.primeros-pasos')
        </div>
    </div>
@endisset

<h6 class="ayuda-titulo">Temas</h6>
<div class="row g-3 mb-4">
    @foreach($principales as $tema)
        <div class="col-sm-6 col-xl-4 ayuda-tema" data-buscar="{{ $tema['buscar'] }}">
            <a href="{{ route('admin.ayuda.show', $tema['slug']) }}" class="card h-100 text-decoration-none text-body ayuda-tarjeta">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="ayuda-icono"><i class="bi {{ $tema['icono'] }}"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $tema['titulo'] }}</div>
                        <div class="small text-muted">{{ $tema['resumen'] }}</div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

@isset($temas['preguntas-frecuentes'])
    <h6 class="ayuda-titulo">Preguntas frecuentes</h6>
    <div class="mb-4" style="max-width: 760px;" id="ayuda-preguntas">
        @include('admin.ayudas.preguntas-frecuentes')
    </div>
@endisset

@push('scripts')
<script>
(function () {
    var buscador = document.getElementById('buscar-ayuda');
    var nada = document.getElementById('ayuda-sin-resultados');

    // Sin tildes ni mayúsculas: "categoria" encuentra "Categoría".
    function plano(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    buscador.addEventListener('input', function () {
        var palabras = plano(buscador.value).split(/\s+/).filter(Boolean);
        var alguno = false;
        document.querySelectorAll('.ayuda-tema, .ayuda-pregunta').forEach(function (el) {
            var texto = plano(el.dataset.buscar);
            var esta = palabras.every(function (p) { return texto.indexOf(p) !== -1; });
            el.classList.toggle('d-none', !esta);
            if (esta) alguno = true;
            // Una pregunta que coincide se abre sola.
            if (el.tagName === 'DETAILS') el.open = esta && palabras.length > 0;
        });
        nada.classList.toggle('d-none', alguno);
    });
    buscador.focus();
})();
</script>
@endpush
@endsection
