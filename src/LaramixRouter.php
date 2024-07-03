<?php

namespace Laramix\Laramix;

class LaramixRouter
{
    public function routes(): \Illuminate\Support\Collection
    {

        $directory = app(Laramix::class)->routeDirectory();
        $files = collect(scandir($directory))
            ->filter(fn ($file) => str($file)->endsWith(['.tsx', '.php']))
            ->map(fn ($file) => str($file)->replaceLast('.tsx', '')->replaceLast('.php', '')->toString())
            ->values();
        $hasRoot = $files->contains('_root');

        $routes = $files->reduce(function ($routes, $file) use ($hasRoot) {
            $partsSoFar = '';
            $parts = str($file)->explode('.')->map(function ($part) use (&$partsSoFar) {
                $isSilent = str($part)->startsWith('_');
                $nextDoesntNest = str($part)->endsWith('_');
                $isVariable = str($part)->startsWith('$');
                $isOptional = str($part)->startsWith('(') && str($part)->endsWith(')');
                $isOptionalVariable = str($part)->startsWith('($') && str($part)->endsWith(')');

                $laravelRouteComponent = '';
                $laravelRoutePart = $part;

                if(str($laravelRoutePart)->endsWith('_')){
                    $laravelRoutePart = str($laravelRoutePart)->replaceLast('_', '');
                }
                if (str($laravelRoutePart)->startsWith('_'))  {
                    $laravelRoutePart = str($laravelRoutePart)->replaceFirst('_', '');
                }
                if ($isVariable) {
                    $laravelRouteComponent = '{'.str($laravelRoutePart)->replace('$', '').'}';
                } elseif ($isOptionalVariable) {
                    $laravelRouteComponent = '{'.str($laravelRoutePart)->replace('($', '')->replace(')', '').'?}';
                } elseif ($isOptional) {
                    $laravelRouteComponent = '{'.str($laravelRoutePart)->replace('(', '')->replace(')', '').'?}';
                } elseif ($isSilent) {
                    $laravelRouteComponent = '';
                } elseif ($nextDoesntNest) {
                    $laravelRouteComponent = str($laravelRoutePart)->replaceLast('_', '');
                } else {
                    $laravelRouteComponent = $laravelRoutePart;
                }
                $component = $partsSoFar ? $partsSoFar.'.'.$part : $part;
                $partsSoFar = $component;
                $component = $nextDoesntNest ? null : $partsSoFar;

                return [$laravelRouteComponent, $component];
            });

            if ($hasRoot && $parts->first()[1] !== '_root') {
                $parts->unshift(['', '_root']);
            }


            $route = new LaramixRoute(
                $parts->map(fn ($part) => $part[0])->join('/'),
                $parts->map(fn ($part) => $part[1])->filter()->join('|'),
                $parts->map(fn ($part) => $part[1])->filter()
                    ->reduce(function($middleware, $componentName) {

                     $middleware = array_merge($middleware,
                         app(Laramix::class)->component($componentName)->middlewareFor('props') ?? []);
                     $middleware = array_unique($middleware);
                    return $middleware;
                    }, [])
            );
            // This is a layout file, not a route.
            if ($parts->last()[0] === '' && ! str($parts->last()[1])->endsWith('_index')) {
                $route->isLayout = true;
            }
            $routes->push($route);

            return $routes;
        }, collect([]))
            ->sort(function (LaramixRoute $a, LaramixRoute $b) {
                return strlen($a->getPath()) - strlen($b->getPath());
            });

        return $routes;
    }



    public function componentActionRoutes() {
        $actionRoutes = collect([]);
        foreach ($this->routes() as $route) {
            $routeComponentNames = str($route->name)->explode('|');
            $lastComponent = app(Laramix::class)->component($routeComponentNames->last());

            foreach ($lastComponent->actions() as $actionName => $actionValue) {
                $middleware = array_merge($route->middleware,  $lastComponent->middlewareFor($actionName) ?? []);
                $actionRoutes[] = new LaramixRoute('_laramix/' . $lastComponent->getName() . '/' . $actionName, $route->getName() . '.' . $actionName, $middleware);
            }
        }

        return $actionRoutes;
    }

    public function resolve(string $routeName): LaramixRoute
    {

        return $this->routes()->firstWhere(function (LaramixRoute $route) use ($routeName) {
            return $route->getName() === $routeName;
        });
    }
}
