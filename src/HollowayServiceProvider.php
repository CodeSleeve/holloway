<?php

namespace CodeSleeve\Holloway;

use Illuminate\Support\ServiceProvider;

class HollowayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Mapper::setConnectionResolver($this->app['db']);

        Mapper::setEventManager($this->app['events']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
