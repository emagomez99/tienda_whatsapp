<?php

use App\Models\Etiqueta;
use App\Models\EtiquetaValor;
use App\Models\Menu;
use App\Models\Proveedor;
use App\Services\CaracteristicasDesdeProveedores;
use App\Services\CategoriasDesdeEtiquetas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pasa lo viejo a lo nuevo y borra lo viejo, en ese orden y en una sola migración
 * (plan de Categorías, sección 6).
 *
 *  1. Las etiquetas "Categoria" y "Subcategoria" pasan a categorías, y los menús que
 *     filtraban por ellas, a "Los de una categoría" (CategoriasDesdeEtiquetas).
 *     Los productos de Hercules van a "Kits hidráulicos" (decisión D6).
 *  2. Las reglas por proveedor (proveedor_etiqueta) pasan a las categorías
 *     (CaracteristicasDesdeProveedores).
 *  3. Se borran esas dos etiquetas (con sus valores, en cascada) y la tabla de
 *     reglas por proveedor, que ya nadie usa.
 *
 * Va todo junto a propósito: con una copia nueva de la base de un cliente alcanza
 * con `php artisan tenants:migrate`, y da siempre lo mismo. Si la limpieza fuera
 * otra migración aparte, podría correr antes de pasar los datos y perderlos.
 *
 * En una base donde ya se pasó todo (corriendo los comandos de antes), los pasos 1
 * y 2 no agregan nada: sólo agregan lo que falta.
 *
 * Una etiqueta vieja que todavía usa un menú (uno que no se pudo convertir) no se
 * borra: se avisa, para revisarlo a mano.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('proveedor_etiqueta')) {
            $porProveedor = Proveedor::where('nombre', 'ilike', 'Hercules')->exists()
                ? ['Hercules' => 'Kits hidráulicos']
                : [];

            (new CategoriasDesdeEtiquetas())->ejecutar($porProveedor);
            (new CaracteristicasDesdeProveedores())->ejecutar();
        }

        foreach ($this->etiquetasViejas() as $etiqueta) {
            $usadaPor = Menu::where('tipo_enlace', Menu::TIPO_ETIQUETA)->where('enlace_id', $etiqueta->id)->pluck('nombre');
            if ($usadaPor->isNotEmpty()) {
                echo "  Se conserva la etiqueta «{$etiqueta->nombre}»: la usa el menú «" . $usadaPor->implode('», «') . "». Revisalo a mano.\n";
                continue;
            }

            // Los menús que la ofrecían como filtro en cascada dejan de ofrecerla.
            Menu::whereNotNull('filtros_etiquetas')->get()->each(function (Menu $menu) use ($etiqueta) {
                $filtros = array_values(array_diff(array_map('intval', (array) $menu->filtros_etiquetas), [$etiqueta->id]));
                if ($filtros !== array_map('intval', (array) $menu->filtros_etiquetas)) {
                    $config = (array) $menu->filtros_config;
                    unset($config[(string) $etiqueta->id]);
                    $menu->update(['filtros_etiquetas' => $filtros, 'filtros_config' => $config ?: null]);
                }
            });

            $etiqueta->delete();   // valores, productos y reglas, en cascada
        }

        Schema::dropIfExists('proveedor_etiqueta');
    }

    /**
     * Vuelve a crear la tabla de reglas por proveedor, vacía: lo borrado no se puede
     * recuperar (para eso, volver a cargar la copia de la base).
     */
    public function down()
    {
        if (!Schema::hasTable('proveedor_etiqueta')) {
            Schema::create('proveedor_etiqueta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
                $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
                $table->boolean('obligatoria')->nullable();
                $table->timestamps();
                $table->unique(['proveedor_id', 'etiqueta_id']);
            });
        }
    }

    /** "Categoria" y "Subcategoria", con o sin tilde ni mayúsculas. */
    private function etiquetasViejas()
    {
        return Etiqueta::all()->filter(function (Etiqueta $e) {
            return in_array(Str::ascii(EtiquetaValor::normalizar($e->nombre)), [
                CategoriasDesdeEtiquetas::ETIQUETA_CATEGORIA, CategoriasDesdeEtiquetas::ETIQUETA_SUBCATEGORIA,
            ], true);
        });
    }
};
