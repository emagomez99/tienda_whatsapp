{{-- Ayuda de Usuarios y Perfiles (listas y formularios). Ver components/ayuda. Lenguaje simple y ejemplo de supermercado. --}}
<h6 class="ayuda-titulo">Usuarios y perfiles</h6>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-person"></i></span>
    <div><strong>Un usuario es una persona que entra al panel.</strong><br>Con su email y su contraseña. Cada uno tiene la suya: no compartan la misma.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-shield-check"></i></span>
    <div><strong>El perfil dice qué puede hacer.</strong><br>Es una lista de permisos con un nombre (Cajero, Repositor…). Armás el perfil una vez y se lo das a todos los que hacen lo mismo.</div>
</div>

<div class="ayuda-ejemplo">
    <div class="fw-semibold mb-1">Ejemplo: un supermercado</div>
    Perfil <em>Repositor</em>: ver, crear y editar productos (para cargar la <em>Gaseosa Coca-Cola 1,5 L</em> nueva).<br>
    Perfil <em>Cajero</em>: ver y gestionar pedidos.<br>
    Juan y Ana son repositores; Pedro es cajero. Si mañana los repositores también tienen que ver los pedidos, lo cambiás en el perfil y les llega a los dos.
</div>

<h6 class="ayuda-titulo">Para tener en cuenta</h6>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-star"></i></span>
    <div><strong>Superadmin puede todo.</strong><br>Un perfil marcado como Superadmin no mira la lista de permisos: tiene todos.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-eye-slash"></i></span>
    <div><strong>Lo que no puede ver, no le aparece.</strong><br>Si a un perfil le falta, por ejemplo, <em>Ver listado de pedidos</em>, Pedidos no aparece en su menú.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-toggle-off"></i></span>
    <div><strong>¿Alguien deja de trabajar con vos?</strong><br>Apagá <em>Usuario activo</em> en su ficha: ya no puede entrar, y lo que hizo queda registrado a su nombre.</div>
</div>
<div class="ayuda-item">
    <span class="ayuda-icono"><i class="bi bi-shield-x"></i></span>
    <div><strong>¿Cortar el acceso a un grupo entero?</strong><br>Apagá <em>Perfil activo</em> en el perfil: nadie con ese perfil puede entrar hasta que lo vuelvas a prender. Tu propio perfil y el de Superadmin no se pueden apagar, así nadie se queda afuera por error.</div>
</div>
