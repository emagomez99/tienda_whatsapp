<?php

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Tenant;
use Tests\TestCase;

/**
 * Regresión del esquema de URL pública de productos: /producto/{slug}/{id}.
 *
 * El id identifica al producto; el texto que lo precede es decorativo. De ahí salen
 * las dos garantías que este test blinda:
 *   - cualquier texto resuelve, mientras el id sea correcto (301 al canónico);
 *   - cambiar el slug NUNCA rompe una URL ya publicada.
 *
 * El id va en su propio segmento y no pegado con guión: ver
 * el_slug_suelto_que_termina_en_numero_no_sirve_otro_producto().
 *
 * Aislamiento: cada test crea un tenant propio (schema Postgres aparte) y lo
 * destruye al terminar. No se usa RefreshDatabase a propósito: apuntaría a la
 * base central de desarrollo y se llevaría puestos los tenants reales.
 */
class ProductoUrlSchemeTest extends TestCase
{
    const TENANT_ID     = 'testurlscheme';
    const TENANT_DOMAIN = 'testurlscheme.test';

    /** @var \App\Models\Tenant */
    protected $tenant;

    /** @var \App\Models\Producto */
    protected $producto;

    protected function setUp(): void
    {
        parent::setUp();

        // Por si una corrida anterior se cortó a la mitad.
        $this->destruirTenantDePrueba();

        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'nombre' => 'Tenant de prueba (URLs)',
            'email'  => 'urls@test.local',
            'plan'   => 'free',
            'activo' => true,
        ]);
        $this->tenant->domains()->create(['domain' => self::TENANT_DOMAIN]);

        // Crear el tenant dispara CreateDatabase + MigrateDatabase de forma síncrona.
        $this->producto = $this->enTenant(function () {
            $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
            $moneda    = Moneda::create(['nombre' => 'Peso', 'codigo' => 'ARS', 'simbolo' => '$']);

            return Producto::create([
                'proveedor_id' => $proveedor->id,
                'moneda_id'    => $moneda->id,
                'descripcion'  => 'Turron con Mani',
                'precio'       => 1500,
                'stock'        => 10,
                'disponible'   => true,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** Borra el tenant de prueba y, con él, su schema completo. */
    protected function destruirTenantDePrueba()
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $tenant = Tenant::find(self::TENANT_ID);

        if ($tenant) {
            $tenant->delete(); // dispara DeleteDatabase -> DROP SCHEMA
        }
    }

    /** Ejecuta un callback dentro del contexto del tenant de prueba. */
    protected function enTenant(callable $callback)
    {
        tenancy()->initialize($this->tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    /** URL cruda bajo /producto/ en el dominio del tenant (identifica por Host). */
    protected function url($path)
    {
        return 'http://' . self::TENANT_DOMAIN . '/producto/' . ltrim($path, '/');
    }

    /** URL canónica de un producto: /producto/{slug}/{id} */
    protected function urlCanonica(Producto $producto)
    {
        return $this->url($producto->slugUrl() . '/' . $producto->id);
    }

    /** @test */
    public function la_url_canonica_responde_200()
    {
        $response = $this->get($this->urlCanonica($this->producto));

        $response->assertStatus(200);
        $response->assertSee('Turron con Mani', false);
    }

    /**
     * El corazón del esquema: el texto se ignora, el id manda.
     *
     * @test
     */
    public function un_slug_inventado_con_el_id_correcto_redirige_301_al_canonico()
    {
        $response = $this->get($this->url('cualquier-verdura/' . $this->producto->id));

        $response->assertStatus(301);
        $response->assertRedirect($this->urlCanonica($this->producto));
    }

    /**
     * Forma corta /producto/{id}: válida, redirige a la canónica con slug.
     *
     * @test
     */
    public function solo_el_id_sin_texto_redirige_301_al_canonico()
    {
        $response = $this->get($this->url($this->producto->id));

        $response->assertStatus(301);
        $response->assertRedirect($this->urlCanonica($this->producto));
    }

    /**
     * El texto sin el segmento de id no resuelve: el id es obligatorio.
     *
     * @test
     */
    public function el_texto_sin_id_da_404()
    {
        $this->get($this->url('turron-con-mani'))->assertStatus(404);
        $this->get($this->url('sin-numero-al-final'))->assertStatus(404);
    }

    /**
     * REGRESIÓN del bug que motivó separar el id en su propio segmento.
     *
     * Con la forma vieja (/producto/{slug}-{id}) había que adivinar por regex dónde
     * terminaba el slug, y un slug que termina en número rompía la adivinanza:
     * /producto/jcb-991-00131 extraía "131" y servía el producto id 131 -- otro
     * producto -- con un 301 permanente, en vez de un 404. Afectaba a 1.651
     * productos del catálogo de oleomc.
     *
     * @test
     */
    public function el_slug_suelto_que_termina_en_numero_no_sirve_otro_producto()
    {
        $otro = $this->enTenant(function () {
            return Producto::create([
                'proveedor_id' => $this->producto->proveedor_id,
                'descripcion'  => 'JCB-991-00' . $this->producto->id,
                'precio'       => 500,
                'stock'        => 1,
                'disponible'   => true,
            ]);
        });

        // El slug termina en el id de OTRO producto: "jcb-991-001" para el id 1.
        $this->assertStringEndsWith((string) $this->producto->id, $otro->slug);

        // Pedido suelto, sin segmento de id: 404. Nunca el producto equivocado.
        $this->get($this->url($otro->slug))->assertStatus(404);

        // Y su canónica sigue llevando al producto correcto.
        $this->get($this->urlCanonica($otro))
            ->assertStatus(200)
            ->assertSee($otro->descripcion, false);
    }

    /** @test */
    public function un_id_inexistente_da_404()
    {
        $this->get($this->url('lo-que-sea/999999'))->assertStatus(404);
        $this->get($this->url('999999'))->assertStatus(404);
    }

    /**
     * TODO(public_id): retrocompatibilidad con las URLs viejas /producto/{uuid},
     * de cuando public_id era la route key.
     *
     * Este test es intencionalmente el que se va a poner en rojo cuando se elimine
     * la columna: sirve de recordatorio de que el borrado es una decisión, no un
     * descuido. Al eliminarla, borrar también este test.
     *
     * @test
     */
    public function una_url_vieja_con_uuid_redirige_301_al_canonico()
    {
        $response = $this->get($this->url($this->producto->public_id));

        $response->assertStatus(301);
        $response->assertRedirect($this->urlCanonica($this->producto));
    }

    /** @test */
    public function un_uuid_inexistente_da_404()
    {
        $this->get($this->url('00000000-0000-4000-8000-000000000000'))->assertStatus(404);
    }

    /**
     * El 301 tiene que arrastrar la query string, o se pierde la atribución de
     * campañas (utm_*). Fue un bug real: el redirect las descartaba.
     *
     * @test
     */
    public function el_301_preserva_la_query_string()
    {
        $response = $this->get(
            $this->url('slug-viejo/' . $this->producto->id) . '?utm_source=facebook&utm_campaign=promo'
        );

        $response->assertStatus(301);

        $location = $response->headers->get('Location');

        // El orden de los parámetros no está garantizado; lo que importa es que estén.
        $this->assertStringContainsString('utm_source=facebook', $location);
        $this->assertStringContainsString('utm_campaign=promo', $location);
        $this->assertStringContainsString('/producto/' . $this->producto->slugUrl() . '/' . $this->producto->id, $location);
    }

    /**
     * La promesa central del diseño: renombrar el slug no rompe nada. No hay tabla
     * de historial porque no hace falta recordar el slug viejo -- ningún texto
     * identificó nunca al producto.
     *
     * @test
     */
    public function cambiar_el_slug_no_rompe_las_urls_ya_publicadas()
    {
        $urlPublicadaAntes = $this->urlCanonica($this->producto);

        $this->enTenant(function () {
            $this->producto->slug = 'nombre-totalmente-distinto';
            $this->producto->save();
        });

        $response = $this->get($urlPublicadaAntes);

        $response->assertStatus(301);
        $response->assertRedirect($this->url('nombre-totalmente-distinto/' . $this->producto->id));

        // Y la nueva responde directo, sin rebotes.
        $this->get($this->url('nombre-totalmente-distinto/' . $this->producto->id))
            ->assertStatus(200);
    }

    /**
     * Un producto cuya descripción no deja slug (ej. "!!!") usa el slug por defecto
     * en la URL, para no dejar un segmento vacío, y tiene que resolver igual.
     *
     * @test
     */
    public function un_producto_sin_slug_usa_el_slug_por_defecto()
    {
        $sinSlug = $this->enTenant(function () {
            return Producto::create([
                'proveedor_id' => $this->producto->proveedor_id,
                'descripcion'  => '!!!',
                'precio'       => 100,
                'stock'        => 1,
                'disponible'   => true,
            ]);
        });

        $this->assertNull($sinSlug->slug);
        $this->assertSame(Producto::SLUG_POR_DEFECTO, $sinSlug->slugUrl());

        $this->get($this->urlCanonica($sinSlug))->assertStatus(200);
        $this->get($this->url($sinSlug->id))->assertStatus(301);
    }
}
