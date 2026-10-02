@if($pedido->esPendiente())
    <span class="badge bg-warning text-dark">Pendiente</span>
@elseif($pedido->esConfirmado())
    <span class="badge bg-success">Confirmado</span>
@else
    <span class="badge bg-danger">Cancelado</span>
@endif
