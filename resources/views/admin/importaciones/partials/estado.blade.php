{{-- El estado de una importación, en palabras. Recibe $estado. --}}
@php
    $estados = [
        App\Models\Importacion::REVISANDO => ['Sin aplicar', 'text-bg-warning'],
        App\Models\Importacion::APLICANDO => ['A medio aplicar', 'text-bg-info'],
        App\Models\Importacion::TERMINADA => ['Aplicada', 'text-bg-success'],
        App\Models\Importacion::CANCELADA => ['No se aplicó', 'text-bg-secondary'],
    ];
    [$texto, $clase] = $estados[$estado] ?? [$estado, 'text-bg-light'];
@endphp
<span class="badge rounded-pill {{ $clase }}">{{ $texto }}</span>
