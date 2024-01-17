<?php

namespace alextrellez\QueueMonitor\Tests;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route as RouteFacade;
use alextrellez\QueueMonitor\Controllers\ShowQueueMonitorController;

class RoutesTest extends TestCase
{
    public function testBasicRouteCreation()
    {
        RouteFacade::prefix('jobs')->group(function () {
            RouteFacade::queueMonitor();
        });

        $this->assertInstanceOf(Route::class, $route = app(Router::class)->getRoutes()->getByAction(ShowQueueMonitorController::class));

        $this->assertEquals('jobs', $route->uri);
        $this->assertEquals('\alextrellez\QueueMonitor\Controllers\ShowQueueMonitorController', $route->getAction('controller'));
    }

    public function testRouteCreationInNamespace()
    {
        RouteFacade::namespace('App\Http')->prefix('jobs')->group(function () {
            RouteFacade::queueMonitor();
        });

        $this->assertInstanceOf(Route::class, $route = app(Router::class)->getRoutes()->getByAction(ShowQueueMonitorController::class));

        $this->assertEquals('jobs', $route->uri);
        $this->assertEquals('\alextrellez\QueueMonitor\Controllers\ShowQueueMonitorController', $route->getAction('controller'));
    }
}
