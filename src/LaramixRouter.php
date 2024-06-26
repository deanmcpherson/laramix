<?php

namespace Laramix\Laramix;

use Laramix\Laramix\Data\ExampleDTO;

class LaramixRouter {
    public function routes() : \Illuminate\Support\Collection {


        $directory = app(Laramix::class)->routeDirectory();
        $files = collect(scandir($directory))
            ->filter(fn($file) => str($file)->endsWith('.tsx'))
            ->map(fn($file) =>  str($file)->replaceLast('.tsx', '')->toString())
            ->values();
        $hasRoot = $files->contains('_root');

        $routes = $files->reduce(function($routes, $file) use ($hasRoot, $files) {
            $partsSoFar = '';
            $parts = str($file)->explode('.')->map(function($part) use (&$partsSoFar) {
                $isSilent = str($part)->startsWith('_');
                $nextDoesntNest = str($part)->endsWith('_');
                $isVariable = str($part)->startsWith('$');
                $isOptional = str($part)->startsWith('(') && str($part)->endsWith(')');
                $isOptionalVariable = str($part)->startsWith('($') && str($part)->endsWith(')');

                $laravelRouteComponent = '';
                if ($isVariable) {
                    $laravelRouteComponent = '{' . str($part)->replace('$', '') . '}';
                } else if ($isOptionalVariable) {
                    $laravelRouteComponent = '{' . str($part)->replace('($', '')->replace(')', '') . '?}';
                } else if ($isOptional) {
                    $laravelRouteComponent = '{' . str($part)->replace('(', '')->replace(')', '') . '?}';
                } else if ($isSilent) {
                    $laravelRouteComponent = '';
                } else if ($nextDoesntNest) {
                    $laravelRouteComponent = str($part)->replaceLast('_', '');
                }
                else {
                    $laravelRouteComponent = $part;
                }
                $component = $partsSoFar ? $partsSoFar . '.' . $part : $part;
                $partsSoFar = $component;
                $component = $nextDoesntNest ? null : $partsSoFar;
                return [$laravelRouteComponent, $component];
            });


            if ($hasRoot) {
                $parts->unshift(['', '_root']);
            }
            // This is a layout file, not a route.
            if ($parts->last()[0] === '' && !str($parts->last()[1])->endsWith('_index')) {
                return $routes;
            }
            $routes->push(new LaramixRoute(
                $parts->map(fn($part) => $part[0])->join('/'),
                $parts->map(fn($part) => $part[1])->filter()->join('|')
            ));

            return $routes;
        }, collect([]))
        ->sort(function(LaramixRoute $a, LaramixRoute $b) {
            return  strlen($a->getPath()) - strlen($b->getPath());
        });

        return $routes;
    }

    public function resolve(string $routeName) : LaramixRoute {

        return ($this->routes()->firstWhere(function(LaramixRoute $route) use ($routeName) {
            return $route->getName() === $routeName;
        }));
    }
}
