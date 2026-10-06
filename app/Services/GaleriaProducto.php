<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Las imágenes de un producto como una sola lista ordenada: la primera es la
 * principal (productos.url_imagen) y el resto son las adicionales
 * (producto_imagenes, en ese orden).
 *
 * El formulario manda la galería entera tal como quedó, como fichas en orden:
 *
 *   principal   la principal que ya tenía el producto
 *   id:15       una adicional que ya tenía (producto_imagenes.id)
 *   url:https…  una imagen nueva por URL
 *   archivo:0   una imagen nueva subida, índice en galeria_archivos[]
 *
 * Así cambiar la principal, reordenar, agregar y quitar son lo mismo: la lista
 * nueva. Reemplaza a los siete campos que tenía cada caso por separado.
 */
class GaleriaProducto
{
    const PATRON_FICHA = '/^(principal|id:\d+|url:https?:\/\/\S+|archivo:\d+)$/i';

    /** Cuántas imágenes admite un producto en total, principal incluida. */
    public static function maximo(): int
    {
        return Configuracion::imagenesAdicionalesActivas()
            ? 1 + Configuracion::maxImagenesAdicionales()
            : 1;
    }

    public static function reglas(): array
    {
        return [
            'galeria'            => 'nullable|array|max:' . static::maximo(),
            'galeria.*'          => ['string', 'max:2100', 'regex:' . static::PATRON_FICHA],
            'galeria_archivos'   => 'nullable|array',
            'galeria_archivos.*' => 'image|max:2048',
        ];
    }

    public static function mensajes(): array
    {
        return [
            'galeria.max'                => 'Se pueden cargar hasta :max imágenes por producto.',
            'galeria.*.regex'            => 'Una de las imágenes no es válida: si es una URL, tiene que empezar con http:// o https://.',
            'galeria_archivos.*.image'   => 'Uno de los archivos no es una imagen.',
            'galeria_archivos.*.max'     => 'Cada imagen puede pesar hasta 2 MB.',
        ];
    }

    /**
     * Deja la galería del producto como dice la lista. Las imágenes subidas que
     * quedan fuera se borran del disco; las URL externas no son nuestras.
     *
     * @param string[]       $fichas
     * @param UploadedFile[] $archivos
     */
    public function sincronizar(Producto $producto, array $fichas, array $archivos): void
    {
        $anteriores = $this->urlsActuales($producto);
        $nuevas     = [];

        foreach (array_slice($fichas, 0, static::maximo()) as $ficha) {
            $url = $this->resolver($producto, (string) $ficha, $archivos);
            if ($url !== null && !in_array($url, $nuevas, true)) {
                $nuevas[] = $url;
            }
        }

        DB::transaction(function () use ($producto, $nuevas) {
            $producto->forceFill(['url_imagen' => $nuevas[0] ?? null])->save();

            $producto->imagenes()->delete();
            foreach (array_slice($nuevas, 1) as $orden => $url) {
                ProductoImagen::create(['producto_id' => $producto->id, 'url' => $url, 'orden' => $orden]);
            }
        });

        foreach (array_diff($anteriores, $nuevas) as $url) {
            if (!static::esExterna($url)) {
                Storage::disk('public')->delete($url);
            }
        }

        $producto->unsetRelation('imagenes');
    }

    /** @return string[] la principal primero y después las adicionales en orden */
    private function urlsActuales(Producto $producto): array
    {
        $urls = $producto->getOriginal('url_imagen') ? [$producto->getOriginal('url_imagen')] : [];

        return array_merge($urls, $producto->imagenes()->orderBy('orden')->orderBy('id')->pluck('url')->all());
    }

    private function resolver(Producto $producto, string $ficha, array $archivos): ?string
    {
        if ($ficha === 'principal') {
            return $producto->getOriginal('url_imagen') ?: null;
        }

        [$tipo, $dato] = explode(':', $ficha, 2) + [1 => ''];

        switch (strtolower($tipo)) {
            case 'id':
                return $producto->imagenes()->whereKey((int) $dato)->value('url');
            case 'url':
                return $dato;
            case 'archivo':
                $archivo = $archivos[(int) $dato] ?? null;
                return $archivo instanceof UploadedFile
                    ? $archivo->store(tenant('id') . '/productos', 'public')
                    : null;
        }

        return null;
    }

    public static function esExterna(string $url): bool
    {
        return stripos($url, 'http') === 0;
    }
}
