<?php

namespace Laramix\Laramix;

use Laramix\Laramix\Controllers\LaramixController;
use Illuminate\Support\Facades\Route;

class Laramix {

    public function routeDirectory() {
        return resource_path('js/routes');
    }
    public function routes() {
        //actions route
        Route::post('/laramix/{component}/{action}', [LaramixController::class, 'action'])->name('laramix.action');
        //view routes
        app(LaramixRouter::class)
            ->routes()
            ->each(function(LaramixRoute $route) {
            Route::get($route->getPath(), [LaramixController::class, 'view'])->name($route->getName());
        });
    }

    public function route(string $routeName) : LaramixRoute {
        return app(LaramixRouter::class)->resolve($routeName);
    }

    public function routesManifest() {
        $routes = app(LaramixRouter::class)->routes()->map(function(LaramixRoute $route) {
            return $route->toManifest();
        })->values();

        $components = collect(scandir($this->routeDirectory()))
            ->filter(fn($file) => str($file)->endsWith('.tsx'))
            ->map(fn($file) =>  str($file)->replaceLast('.tsx', '')->toString())
            ->values()
            ->map(fn($componentName) => $this->component($componentName)->toManifest())
            ->values();

        return [
            'routes' => $routes,
            'components' => $components
        ];

    }


    public function component(string $componentName) : LaramixComponent {
        $filePath = $this->routeDirectory() . '/' . $componentName . '.tsx';
        return new LaramixComponent(
            filePath: $filePath,
            name: $componentName
        );
    }
}
