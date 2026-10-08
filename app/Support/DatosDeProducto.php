<?php

namespace App\Support;

use App\Models\EtiquetaValor;
use Illuminate\Http\UploadedFile;

/**
 * Lo que se quiere guardar de un producto, venga del formulario o de una planilla.
 *
 * Cada parte opcional distingue "no tocar" (null, el valor por defecto) de "dejar
 * vacío" ([]): una planilla que no trae la columna de especificaciones no tiene que
 * borrarlas, mientras que el formulario, que siempre las manda, sí las reemplaza.
 *
 * Inmutable: cada con...() devuelve una copia.
 */
final class DatosDeProducto
{
    /** @var array columnas de productos, ya validadas en su formato */
    private $atributos;

    /** @var int|null sólo en el alta; entra como movimiento de stock */
    private $stockInicial = null;

    /** @var int[]|null ids de categorías */
    private $categorias = null;

    /** @var array<int, string[]>|null etiqueta_id => valores (uno, o varios si la característica lo admite) */
    private $etiquetas = null;

    /** @var array|null [['clave' => ..., 'valor' => ...], ...] */
    private $especificaciones = null;

    /** @var array|null fichas de GaleriaProducto, en orden */
    private $galeria = null;

    /** @var UploadedFile[] */
    private $archivosGaleria = [];

    public function __construct(array $atributos)
    {
        $this->atributos = $atributos;
    }

    public function conStockInicial(int $cantidad): self
    {
        $copia = clone $this;
        $copia->stockInicial = $cantidad;
        return $copia;
    }

    /** @param int[] $ids categorías asignadas a mano (las de arriba se calculan) */
    public function conCategorias(array $ids): self
    {
        $copia = clone $this;
        $copia->categorias = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return $copia;
    }

    /**
     * @param array $valores etiqueta_id => valor o lista de valores. Los vacíos se
     *        descartan, y un valor repetido ("M" y "m") cuenta una vez.
     */
    public function conEtiquetas(array $valores): self
    {
        $copia = clone $this;
        $copia->etiquetas = [];
        foreach ($valores as $etiquetaId => $lista) {
            if (!$etiquetaId) {
                continue;
            }
            $vistos = [];
            foreach ((array) $lista as $valor) {
                $valor = trim((string) $valor);
                $clave = EtiquetaValor::normalizar($valor);
                if ($valor !== '' && !isset($vistos[$clave])) {
                    $vistos[$clave] = true;
                    $copia->etiquetas[(int) $etiquetaId][] = $valor;
                }
            }
        }
        return $copia;
    }

    /** @param array $pares [['clave' => ..., 'valor' => ...]]; las incompletas se descartan */
    public function conEspecificaciones(array $pares): self
    {
        $copia = clone $this;
        $copia->especificaciones = [];
        foreach ($pares as $par) {
            $clave = trim((string) ($par['clave'] ?? ''));
            $valor = trim((string) ($par['valor'] ?? ''));
            if ($clave !== '' && $valor !== '') {
                $copia->especificaciones[] = ['clave' => $clave, 'valor' => $valor];
            }
        }
        return $copia;
    }

    /** @param UploadedFile[] $archivos */
    public function conGaleria(array $fichas, array $archivos = []): self
    {
        $copia = clone $this;
        $copia->galeria = array_values($fichas);
        $copia->archivosGaleria = $archivos;
        return $copia;
    }

    public function atributos(): array
    {
        return $this->atributos;
    }

    public function atributo(string $nombre, $porDefecto = null)
    {
        return array_key_exists($nombre, $this->atributos) ? $this->atributos[$nombre] : $porDefecto;
    }

    public function stockInicial(): ?int
    {
        return $this->stockInicial;
    }

    public function categorias(): ?array
    {
        return $this->categorias;
    }

    public function etiquetas(): ?array
    {
        return $this->etiquetas;
    }

    public function especificaciones(): ?array
    {
        return $this->especificaciones;
    }

    public function galeria(): ?array
    {
        return $this->galeria;
    }

    /** @return UploadedFile[] */
    public function archivosGaleria(): array
    {
        return $this->archivosGaleria;
    }
}
