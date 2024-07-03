<?php

namespace Laramix\Laramix;

use Illuminate\Support\Facades\Route;
use Laramix\Laramix\Controllers\LaramixController;

class Laramix
{
    public function routeDirectory()
    {
        return resource_path('js/routes');
    }

    public function routes()
    {
        //view routes

        $router = app(LaramixRouter::class);

        $router->componentActionRoutes()->each(function (LaramixRoute $route) {
            Route::middleware($route->middleware)->post($route->getPath(), [LaramixController::class, 'action'])
                ->name($route->getName());
        });

        $router
            ->routes()
            ->each(function (LaramixRoute $route) {
                if ($route->isLayout) {
                    return;
                }

                Route::middleware($route->middleware)->get($route->getPath(), [LaramixController::class, 'view'])
                    ->name($route->getName());
            });
    }

    public function route(string $routeName): LaramixRoute
    {
        return app(LaramixRouter::class)->resolve($routeName);
    }

    public function routesManifest()
    {
        $routes = app(LaramixRouter::class)->routes()
            ->filter(fn (LaramixRoute $route) => ! $route->isLayout)
            ->map(function (LaramixRoute $route) {
                return $route->toManifest();
            })->values();

        $components = collect(scandir($this->routeDirectory()))
            ->filter(fn ($file) => str($file)->endsWith(['.tsx', '.php']))
            ->map(fn ($file) => str($file)
                ->replaceLast('.tsx', '')
                ->replaceLast('.php', '')
                ->toString())
            ->values()
            ->map(fn ($componentName) => $this->component($componentName)->toManifest())
            ->values();

        return [
            'routes' => $routes,
            'components' => $components,
        ];

    }

    public function actionsTypeScript()
    {
        $manifest = $this->routesManifest();
        $items = [];
        foreach ($manifest['components'] as $value) {
            $component = $value['component'];
            $items[] = "\"$component\": $component.Props['actions']";

        }

        return '{'.implode(";\n", $items).'}';
    }

    public function component(string $componentName): LaramixComponent
    {
        $filePath = $this->routeDirectory().'/'.$componentName.'.tsx';
        if (! file_exists($filePath)) {
            $filePath = $this->routeDirectory().'/'.$componentName.'.php';
        }

        return new LaramixComponent(
            filePath: $filePath,
            name: $componentName
        );
    }
}
