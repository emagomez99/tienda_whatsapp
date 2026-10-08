<?php

namespace App\Support;

/**
 * Buscar como busca la gente: sin tildes, sin ñ y palabra por palabra.
 *
 * "termometro" encuentra "Termómetro", "panales" encuentra "Pañales" y "shampoo
 * caspa" encuentra "Shampoo anticaspa": cada palabra tiene que estar en algún lado,
 * en cualquier orden. Lo encontró la prueba con una farmacia, donde los clientes
 * escriben rápido y sin tildes.
 *
 * Se resuelve con translate() de PostgreSQL, en los dos lados de la comparación,
 * en vez de la extensión unaccent: no hace falta instalar nada en el servidor.
 *
 * Uso:
 *     foreach (BusquedaDeTexto::palabras($texto) as $palabra) {
 *         $patron = BusquedaDeTexto::patron($palabra);
 *         $query->where(function ($q) use ($patron) {
 *             BusquedaDeTexto::contiene($q, 'descripcion', $patron);
 *             BusquedaDeTexto::contiene($q, 'id_proveedor', $patron);
 *         });
 *     }
 */
class BusquedaDeTexto
{
    /** Cada letra de la primera lista se compara como la de la misma posición en la segunda. */
    const CON_TILDE = 'áàäâãéèëêíìïîóòöôõúùüûñç';
    const SIN_TILDE = 'aaaaaeeeeiiiiooooouuuunc';

    /** @return string[] las palabras del texto, en minúscula y sin tildes */
    public static function palabras(string $texto): array
    {
        $plano = static::aplanar($texto);

        return array_values(array_unique(array_filter(preg_split('/\s+/u', $plano), 'strlen')));
    }

    /** El patrón LIKE para una palabra ya aplanada: contiene, sin comodines del usuario. */
    public static function patron(string $palabra): string
    {
        return '%' . addcslashes($palabra, '\\%_') . '%';
    }

    /**
     * Agrega "la columna contiene el patrón" a la consulta, con OR por defecto: cada
     * palabra puede estar en cualquiera de las columnas.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    public static function contiene($query, string $columna, string $patron, string $boolean = 'or')
    {
        return $query->whereRaw(static::columnaPlana($columna) . ' like ?', [$patron], $boolean);
    }

    /** La expresión SQL de una columna en minúscula y sin tildes, igual que aplanar(). */
    public static function columnaPlana(string $columna): string
    {
        return "translate(lower({$columna}), '" . self::CON_TILDE . "', '" . self::SIN_TILDE . "')";
    }

    /** Minúscula y sin tildes, igual que columnaPlana() en la base. */
    public static function aplanar(string $texto): string
    {
        $origen  = preg_split('//u', self::CON_TILDE, -1, PREG_SPLIT_NO_EMPTY);
        $destino = preg_split('//u', self::SIN_TILDE, -1, PREG_SPLIT_NO_EMPTY);

        return strtr(mb_strtolower(trim($texto), 'UTF-8'), array_combine($origen, $destino));
    }
}
