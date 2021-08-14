<?php

namespace App\Providers;

use App\Models\Offer;
use App\Models\Trade;
use App\Policies\OfferPolicy;
use App\Policies\TradePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Offer::class => OfferPolicy::class,
        Trade::class => TradePolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
