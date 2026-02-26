<?php

namespace B2BClassEdit\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

class B2BClassEditRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router)
    {
        $router->post('b2b-class-edit/switch/{contactId}', 'B2BClassEdit\\Controllers\\ClassSwitchController@trigger');
    }
}
