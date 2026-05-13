@if ($paginator->hasPages())
<nav aria-label="Paginación" class="pagination-wrapper">
    {{-- Info solo en desktop --}}
    <div class="pagination-info text-muted mb-3 text-center">
        <small>
            Mostrando
            <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
            -
            <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
            de
            <span class="fw-semibold">{{ $paginator->total() }}</span>
        </small>
    </div>

    {{-- Paginación desktop: ventana de 5 números --}}
    <ul class="pagination pagination-modern justify-content-center flex-wrap gap-1 mb-0 d-none d-sm-flex">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-double-left"></i></span></li>
        @else
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->url(1) }}"><i class="bi bi-chevron-double-left"></i></a></li>
        @endif

        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-left"></i></span></li>
        @else
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-left"></i></a></li>
        @endif

        @php
            $start = max($paginator->currentPage() - 2, 1);
            $end   = min($paginator->currentPage() + 2, $paginator->lastPage());
            if ($paginator->currentPage() <= 3) $end = min(5, $paginator->lastPage());
            if ($paginator->currentPage() >= $paginator->lastPage() - 2) $start = max($paginator->lastPage() - 4, 1);
        @endphp

        @if ($start > 1)
            <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
            @if ($start > 2)<li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>@endif
        @endif

        @for ($page = $start; $page <= $end; $page++)
            @if ($page == $paginator->currentPage())
                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
            @endif
        @endfor

        @if ($end < $paginator->lastPage())
            @if ($end < $paginator->lastPage() - 1)<li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>@endif
            <li class="page-item"><a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a></li>
        @endif

        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->nextPageUrl() }}" rel="next"><i class="bi bi-chevron-right"></i></a></li>
        @else
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-right"></i></span></li>
        @endif

        @if ($paginator->currentPage() == $paginator->lastPage())
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-double-right"></i></span></li>
        @else
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->url($paginator->lastPage()) }}"><i class="bi bi-chevron-double-right"></i></a></li>
        @endif
    </ul>

    {{-- Paginación mobile: [<] [1] [2] [3] [última] [>] --}}
    @php
        $ultimo = $paginator->lastPage();
        $actual = $paginator->currentPage();
    @endphp
    <ul class="pagination pagination-modern justify-content-center gap-1 mb-0 d-flex d-sm-none">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-left"></i></span></li>
        @else
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-left"></i></a></li>
        @endif

        @foreach ([1, 2, 3] as $p)
            @if ($p <= $ultimo)
                <li class="page-item{{ $actual == $p ? ' active' : '' }}">
                    @if ($actual == $p)
                        <span class="page-link">{{ $p }}</span>
                    @else
                        <a class="page-link" href="{{ $paginator->url($p) }}">{{ $p }}</a>
                    @endif
                </li>
            @endif
        @endforeach

        @if ($actual > 3 && $actual < $ultimo)
            <li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>
            <li class="page-item active" aria-current="page"><span class="page-link">{{ $actual }}</span></li>
        @endif

        @if ($ultimo > 3)
            @if ($actual < $ultimo && $actual <= 3)
                <li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>
            @endif
            <li class="page-item{{ $actual == $ultimo ? ' active' : '' }}">
                @if ($actual == $ultimo)
                    <span class="page-link">{{ $ultimo }}</span>
                @else
                    <a class="page-link" href="{{ $paginator->url($ultimo) }}">{{ $ultimo }}</a>
                @endif
            </li>
        @endif

        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link page-link-nav" href="{{ $paginator->nextPageUrl() }}" rel="next"><i class="bi bi-chevron-right"></i></a></li>
        @else
            <li class="page-item disabled"><span class="page-link page-link-nav"><i class="bi bi-chevron-right"></i></span></li>
        @endif
    </ul>
</nav>

<style>
.pagination-wrapper {
    padding: 1rem 0;
}

.pagination-modern {
    gap: 0.25rem;
}

.pagination-modern .page-item .page-link {
    border: none;
    border-radius: 0.5rem;
    padding: 0.5rem 0.85rem;
    font-weight: 500;
    color: #495057;
    background-color: #f8f9fa;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.pagination-modern .page-item .page-link:hover {
    background-color: #e9ecef;
    color: #212529;
    transform: translateY(-1px);
}

.pagination-modern .page-item.active .page-link {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
}

.pagination-modern .page-item.disabled .page-link {
    background-color: #f8f9fa;
    color: #adb5bd;
    cursor: not-allowed;
}

.pagination-modern .page-link-nav {
    background-color: transparent !important;
    padding: 0.5rem 0.6rem;
}

.pagination-modern .page-link-nav:hover {
    background-color: #e9ecef !important;
}

.pagination-modern .page-ellipsis {
    background-color: transparent !important;
    cursor: default;
    padding: 0.5rem 0.3rem;
}

.pagination-modern .page-ellipsis:hover {
    transform: none;
}
</style>
@endif
