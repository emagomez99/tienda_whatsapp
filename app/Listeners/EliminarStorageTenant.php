<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Events\DeletingTenant;

class EliminarStorageTenant
{
    public function handle(DeletingTenant $event): void
    {
        Storage::disk('public')->deleteDirectory($event->tenant->id);
    }
}
