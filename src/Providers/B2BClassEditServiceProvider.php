<?php

namespace B2BClassEdit\Providers;

use B2BClassEdit\Listeners\CustomerRegistrationListener;
use Plenty\Modules\Account\Contact\Events\AfterContactUpdate;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;

class B2BClassEditServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(Dispatcher $dispatcher)
    {
        $dispatcher->listen(AfterContactUpdate::class, CustomerRegistrationListener::class);
    }
}
