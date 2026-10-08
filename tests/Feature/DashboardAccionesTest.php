<?php

namespace Tests\Feature;

use App\Models\Perfil;
use App\Models\Permiso;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/**
 * Acciones rápidas del dashboard: qué se ofrece, en qué orden, y a quién.
 *
 * El orden importa porque la primera acción es la que se toca sin mirar; el filtro por
 * permisos importa porque ofrecer un botón que después contesta "no tenés permiso" es
 * peor que no ofrecerlo.
 */
class DashboardAccionesTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testacciones';
    const TENANT_DOMAIN = 'testacciones.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            (new PermisoSeeder())->run();
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** Usuario con un perfil que tiene exactamente los permisos indicados. */
    protected function usuarioCon(array $slugs)
    {
        $usuario = $this->enTenant(function () use ($slugs) {
            $perfil = Perfil::create([
                'nombre'        => 'Perfil ' . uniqid(),
                'es_superadmin' => false,
                'activo'        => true,
            ]);

            $perfil->permisos()->sync(
                Permiso::whereIn('nombre', array_merge($slugs, ['dashboard.ver']))->pluck('id')
            );

            return User::create([
                'name'      => 'Usuario de prueba',
                'email'     => uniqid() . '@' . self::TENANT_DOMAIN,
                'password'  => bcrypt('secreto'),
                'is_admin'  => true,
                'activo'    => true,
                'perfil_id' => $perfil->id,
            ]);
        });

        return $this->actingAs($usuario);
    }

    /** @test */
    public function el_pedido_es_la_primera_accion()
    {
        $html = $this->usuarioCon([
            'pedidos.gestionar', 'productos.crear', 'proveedores.crear', 'configuraciones.ver',
        ])->get($this->urlTenant('admin'))->assertStatus(200)->getContent();

        $posiciones = [
            'Crear pedido'    => strpos($html, 'Crear pedido'),
            'Crear producto'  => strpos($html, 'Crear producto'),
            'Crear proveedor' => strpos($html, 'Crear proveedor'),
            // Textos en minúscula y "Ajustes" (antes "Configuración"): la pasada visual
            // del panel unificó los nombres con los del menú lateral.
            'Ajustes'         => strpos($html, 'Logo, colores, WhatsApp y más'),
        ];

        foreach ($posiciones as $etiqueta => $posicion) {
            $this->assertNotFalse($posicion, 'Falta la acción "' . $etiqueta . '".');
        }

        $this->assertSame(
            ['Crear pedido', 'Crear producto', 'Crear proveedor', 'Ajustes'],
            array_keys($posiciones),
            'El orden esperado del arreglo cambió; revisá el test.'
        );

        $anterior = -1;
        foreach ($posiciones as $etiqueta => $posicion) {
            $this->assertGreaterThan($anterior, $posicion, '"' . $etiqueta . '" quedó fuera de orden.');
            $anterior = $posicion;
        }
    }

    /** @test */
    public function el_pedido_es_la_unica_accion_destacada()
    {
        $html = $this->usuarioCon([
            'pedidos.gestionar', 'productos.crear', 'proveedores.crear', 'configuraciones.ver',
        ])->get($this->urlTenant('admin'))->assertStatus(200)->getContent();

        $this->assertSame(1, substr_count($html, 'accion-rapida destacada'));
    }

    /** @test */
    public function solo_se_ofrecen_las_acciones_que_el_usuario_puede_ejecutar()
    {
        $html = $this->usuarioCon(['productos.crear'])
            ->get($this->urlTenant('admin'))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('Crear producto', $html);
        $this->assertStringNotContainsString('Crear pedido', $html);
        $this->assertStringNotContainsString('Crear proveedor', $html);
        $this->assertStringNotContainsString('Logo, colores, WhatsApp y más', $html);
    }

    /**
     * Sin ninguna acción disponible la tarjeta no se dibuja: un encabezado con nada
     * abajo parece un error de la pantalla.
     *
     * @test
     */
    public function sin_acciones_disponibles_no_se_muestra_la_tarjeta()
    {
        $html = $this->usuarioCon([])
            ->get($this->urlTenant('admin'))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('Acciones rápidas', $html);
    }
}
