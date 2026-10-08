<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\User;
use App\Services\CaracteristicasPorCategoria;
use App\Support\Caracteristica;
use Tests\CreaTenantDePrueba;
use Tests\TestCase;

/** Qué características pide cada categoría: herencia, "manda la más cercana", globales y el formulario. */
class CaracteristicasPorCategoriaTest extends TestCase
{
    use CreaTenantDePrueba;

    const TENANT_ID     = 'testcaracteristicas';
    const TENANT_DOMAIN = 'testcaracteristicas.test';

    /** @var array */
    protected $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTenantDePrueba();

        $this->enTenant(function () {
            $e = function ($nombre, $global = false) {
                return Etiqueta::create(['nombre' => $nombre, 'visible_usuarios' => true, 'global' => $global])->id;
            };
            $this->ids = [
                'talle' => $e('Talle'), 'color' => $e('Color'), 'tela' => $e('Tela'), 'marca' => $e('Marca', true),
                'memoria' => $e('Memoria'),
            ];

            $ropa = Categoria::resolverCamino('Ropa', true);
            $remeras = Categoria::resolverCamino('Ropa > Remeras', true);
            $celulares = Categoria::resolverCamino('Celulares', true);

            $ropa->caracteristicas()->sync([
                $this->ids['talle'] => ['obligatoria' => true, 'orden' => 0],
                $this->ids['color'] => ['obligatoria' => false, 'orden' => 1],
            ]);
            // Remeras: suma Tela y vuelve obligatorio Color.
            $remeras->caracteristicas()->sync([
                $this->ids['tela']  => ['obligatoria' => false, 'orden' => 0],
                $this->ids['color'] => ['obligatoria' => true, 'orden' => 1],
            ]);
            $celulares->caracteristicas()->sync([$this->ids['memoria'] => ['obligatoria' => true, 'orden' => 0]]);

            $this->ids += ['ropa' => $ropa->id, 'remeras' => $remeras->id, 'celulares' => $celulares->id];
        });
    }

    protected function tearDown(): void
    {
        $this->destruirTenantDePrueba();

        parent::tearDown();
    }

    /** @param Caracteristica[] $lista */
    private function resumen(array $lista): array
    {
        return array_map(function (Caracteristica $c) {
            return $c->nombre() . ($c->obligatoria() ? '*' : '') . ($c->esGlobal() ? ' (global)' : ' (' . $c->origen() . ')');
        }, $lista);
    }

    /** @test */
    public function una_subcategoria_hereda_y_la_mas_cercana_manda()
    {
        $this->enTenant(function () {
            $servicio = new CaracteristicasPorCategoria();

            $this->assertSame(['Marca (global)', 'Talle* (Ropa)', 'Color (Ropa)'], $this->resumen($servicio->deCategoria($this->ids['ropa'])));
            $this->assertSame(
                ['Marca (global)', 'Talle* (Ropa)', 'Color* (Remeras)', 'Tela (Remeras)'],
                $this->resumen($servicio->deCategoria($this->ids['remeras']))
            );
            $this->assertSame(['Marca (global)', 'Talle* (Ropa)', 'Color (Ropa)'], $this->resumen($servicio->heredadas($this->ids['remeras'])));
        });
    }

    /** @test */
    public function un_producto_con_varias_categorias_suma_y_es_obligatoria_si_alguna_lo_exige()
    {
        $this->enTenant(function () {
            $servicio = new CaracteristicasPorCategoria();

            $this->assertSame(
                ['Marca (global)', 'Talle* (Ropa)', 'Color (Ropa)', 'Memoria* (Celulares)'],
                $this->resumen($servicio->deCategorias([$this->ids['ropa'], $this->ids['celulares']]))
            );
            // Ropa dice Color opcional y Remeras obligatorio: queda obligatorio.
            $nombres = $this->resumen($servicio->deCategorias([$this->ids['ropa'], $this->ids['remeras']]));
            $this->assertContains('Color (Ropa)', array_map(function ($n) { return str_replace('*', '', $n); }, $nombres));
            $this->assertContains('Color* (Ropa)', $nombres);

            $this->assertSame(['Marca (global)'], $this->resumen($servicio->deCategorias([])));
        });
    }

    /** @test */
    public function el_formulario_guarda_en_orden_crea_las_nuevas_sin_duplicar_y_puede_vaciar()
    {
        $admin = $this->enTenant(function () {
            return User::create(['name' => 'Admin', 'email' => 'admin@' . self::TENANT_DOMAIN, 'password' => bcrypt('x'), 'is_admin' => true, 'activo' => true]);
        });

        $this->actingAs($admin)->get($this->urlTenant('admin/categorias/' . $this->ids['remeras'] . '/edit'))
            ->assertOk()
            ->assertSee('Características que pide');

        $this->actingAs($admin)->put($this->urlTenant('admin/categorias/' . $this->ids['celulares']), [
            'nombre' => 'Celulares', 'visible' => 1,
            'caracteristicas' => [
                ['nueva' => 'Almacenamiento', 'obligatoria' => 1],
                ['nueva' => 'color', 'obligatoria' => 0],          // ya existe como "Color"
                ['etiqueta_id' => $this->ids['memoria'], 'obligatoria' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $propias = Categoria::find($this->ids['celulares'])->caracteristicas;
            $this->assertSame(['Almacenamiento', 'Color', 'Memoria'], $propias->pluck('nombre')->all());
            $this->assertSame([true, false, false], $propias->map(function ($e) { return (bool) $e->pivot->obligatoria; })->all());
            $this->assertSame(1, Etiqueta::where('nombre', 'ilike', 'color')->count());
        });

        $this->actingAs($admin)->put($this->urlTenant('admin/categorias/' . $this->ids['celulares']), ['nombre' => 'Celulares', 'visible' => 1])
            ->assertSessionHasNoErrors();

        $this->enTenant(function () {
            $this->assertSame(0, Categoria::find($this->ids['celulares'])->caracteristicas()->count());
        });
    }
}
