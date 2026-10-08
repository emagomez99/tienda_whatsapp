{{--
    Animación corta (12 s, se repite) para entender la diferencia entre categoría y
    característica con el ejemplo del supermercado:
      1. La categoría es el estante: la Coca-Cola entra a «Gaseosas».
      2. La característica es un dato: aparecen sus carteles (Marca, Sabor, Contenido).
      3. Con los datos, el cliente elige: tocá «Marca: Coca-Cola» y quedan sólo ésas.
    Sin JavaScript: todo con CSS (public/css/admin.css, .estante-y-dato). Con
    "reducir movimiento" se ve quieta, con los tres pasos escritos.
    La usan las ayudas de Categorías y de Características.
--}}
<figure class="estante-y-dato" role="img"
        aria-label="Animación: la categoría es el estante donde va el producto (una Coca-Cola entra al estante Gaseosas); la característica es un dato del producto (Marca Coca-Cola, Sabor Cola, 1,5 litros); con los datos el cliente elige (al tocar Marca Coca-Cola quedan sólo las Coca-Cola).">
    <div class="ed-escenario" aria-hidden="true">
        <div class="ed-gondola">
            <div class="ed-cartel"><i class="bi bi-bookshelf"></i> Gaseosas</div>
            <div class="ed-tabla"></div>
        </div>

        <div class="ed-botella ed-coca"><span>Coca</span></div>
        <div class="ed-botella ed-sprite"><span>Sprite</span></div>
        <div class="ed-botella ed-fanta"><span>Fanta</span></div>

        <div class="ed-dato ed-dato-1"><b>Marca:</b> Coca-Cola</div>
        <div class="ed-dato ed-dato-2"><b>Sabor:</b> Cola</div>
        <div class="ed-dato ed-dato-3"><b>Contenido:</b> 1,5 L</div>

        <div class="ed-filtro"><i class="bi bi-funnel-fill"></i> Marca: Coca-Cola</div>
        <i class="bi bi-hand-index-thumb-fill ed-dedo"></i>
    </div>

    <figcaption class="ed-pasos">
        <div class="ed-paso ed-paso-1"><span class="ed-numero">1</span> <strong>La categoría es el estante:</strong> dónde va el producto.</div>
        <div class="ed-paso ed-paso-2"><span class="ed-numero">2</span> <strong>La característica es un dato:</strong> cómo es el producto.</div>
        <div class="ed-paso ed-paso-3"><span class="ed-numero">3</span> <strong>Con los datos, el cliente elige</strong> dentro del estante.</div>
    </figcaption>
</figure>

<div class="ayuda-ejemplo">
    <div class="fw-semibold mb-1">¿Categoría o característica? Tres preguntas</div>
    <div class="mb-1"><i class="bi bi-signpost-2 text-primary"></i> ¿El cliente <strong>entra por ahí</strong> desde el menú? → <strong>Categoría</strong> (Bebidas, Limpieza).</div>
    <div class="mb-1"><i class="bi bi-funnel text-primary"></i> ¿Sirve para <strong>elegir dentro</strong> de un estante? → <strong>Característica</strong> (Sabor, Contenido).</div>
    <div><i class="bi bi-globe text-primary"></i> ¿Lo tiene <strong>casi cualquier producto</strong>? → <strong>Característica</strong>. La marca la tiene una gaseosa, unos fideos y una lavandina: por eso Marca es una característica y no un estante.</div>
</div>
