<?php

namespace App\Providers;

//use Illuminate\Support\ServiceProvider;

use App\Models\Gasto;
use App\Models\LecturaMaquina;
use App\Policies\GastoPolicy;
use App\Policies\LecturaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
     /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        LecturaMaquina::class => LecturaPolicy::class,
        Gasto::class          => GastoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // En Laravel 12 basta con tener $policies; este método las registra.
        $this->registerPolicies();
    }
}
