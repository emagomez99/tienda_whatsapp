<?php

namespace App\Support;

class StockResult
{
    public $cantidad;
    public $error;

    private function __construct($cantidad, $error)
    {
        $this->cantidad = $cantidad;
        $this->error    = $error;
    }

    public static function ok($cantidad)
    {
        return new self($cantidad, null);
    }

    public static function insuficiente($cantidadPermitida, $mensaje)
    {
        return new self($cantidadPermitida, $mensaje);
    }

    public function esSuficiente()
    {
        return $this->error === null;
    }
}
