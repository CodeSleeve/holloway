<?php

namespace Holloway;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Events\Dispatcher;
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

        // Need to figure out how we want to bootstrap this
        Mapper::setEventManager(new Dispatcher);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }
}
