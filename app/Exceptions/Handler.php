<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
        //
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
        $this->renderable(function (TenantCouldNotBeIdentifiedOnDomainException $e) {
            abort(404);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if (!tenancy()->initialized) {
                try {
                    $domain = $request->getHost();
                    $tenant = \App\Models\Tenant::whereHas('domains', function ($q) use ($domain) {
                        $q->where('domain', $domain);
                    })->first();
                    if ($tenant) {
                        tenancy()->initialize($tenant);
                    }
                } catch (\Exception $ex) {
                    // dominio central o tenant no encontrado, se muestra 404 genérico
                }
            }
            // retorna null para que Laravel siga con el renderizado normal de errors/404.blade.php
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
