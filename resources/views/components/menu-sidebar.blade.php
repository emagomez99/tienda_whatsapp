@php
    use App\Models\Menu;
    $menuItems = Menu::getArbolMenu();
@endphp

@if($menuItems->count() > 0)
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="bi bi-list"></i> Menú</h6>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach($menuItems as $menu)
                    @include('components.menu-sidebar-item', ['menu' => $menu, 'nivel' => 0])
                @endforeach
            </ul>
        </div>
    </div>
@endif
