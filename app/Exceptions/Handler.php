<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        // Un dominio que no es de ninguna tienda (un bot que entra por la IP, un
        // dominio viejo) se responde con 404 (ver register): no es un error de la
        // aplicación, y cada uno dejaba su stack trace en laravel.log.
        TenantCouldNotBeIdentifiedOnDomainException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (TenantCouldNotBeIdentifiedOnDomainException $e, $request) {
            return $this->dominioSinTienda($request);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if (tenancy()->initialized) {
                return null;   // errors/404 con el encabezado de la tienda
            }
            $host = $request->getHost();
            if (in_array($host, config('tenancy.central_domains', []), true)) {
                return null;   // la plataforma: errors/404 de siempre
            }
            $tenant = \App\Models\Tenant::whereHas('domains', function ($q) use ($host) {
                $q->where('domain', $host);
            })->first();
            if (!$tenant) {
                return $this->dominioSinTienda($request);
            }
            // Una página que no existe en una tienda: se inicializa para que errors/404
            // muestre su encabezado.
            tenancy()->initialize($tenant);

            return null;
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Un dominio que no es de ninguna tienda. Si entraron con "www." y la tienda está
     * cargada sin él (o al revés), se los manda a la dirección que sí existe, con la
     * misma página. Si no, una página propia sin el encabezado de ninguna tienda (ni
     * el de la plataforma).
     */
    private function dominioSinTienda($request)
    {
        $host = $request->getHost();
        $otro = strpos($host, 'www.') === 0 ? substr($host, 4) : 'www.' . $host;
        if (Domain::where('domain', $otro)->exists()) {
            return redirect()->away($request->getScheme() . '://' . $otro . $request->getRequestUri(), 301);
        }

        return response()->view('errors.tienda-no-encontrada', [], 404);
    }
}
