<?php

namespace Laramix\Laramix;

use Illuminate\Http\RedirectResponse;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\LazyProp;

class LaramixRoute
{
    public function __construct(
        public string $path,
        public string $name,
        public array $middleware = [],
        public bool $isLayout = false,
        public ?string $globalRouteName = null,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGlobalRouteName(): string
    {
        if ($this->globalRouteName) {
            return $this->globalRouteName;
        }

        return $this->name;
    }

    public function components()
    {
        $componentNames = collect(explode('|', $this->name));

        return $componentNames->map(function ($componentName) {
            return app(Laramix::class)->component($componentName);
        })->filter(fn ($component) => $component->exists())->values();
    }

    public function toManifest()
    {
        return [
            'path' => $this->path,
            'components' => $this->components()->map(fn ($component) => $component->getName())->toArray(),
        ];
    }

    public function render()
    {

        $components = $this->components()->map(fn ($component) => $component->props())->toArray();

        $additional = [];

        foreach ($components as $componentIndex => $component) {

            if (is_a($component['props'], RedirectResponse::class)) {
                return $component['props'];
            }
            if (is_array($component['props'])) {
                foreach ($component['props'] as $key => $value) {
                    if (is_a($value, DeferProp::class)) {
                        $additional['late.components.'.$componentIndex.'.'.$key] = $value;
                        unset($components[$componentIndex]['props'][$key]);
                    }
                    if (is_a($value, LazyProp::class)) {
                        $additional['late.components.'.$componentIndex.'.'.$key] = $value;
                        unset($components[$componentIndex]['props'][$key]);
                    }
                }
            }
        }

        return Inertia::render('Laramix', [
            'components' => $components,
            'parameters' => request()->route()->parameters(),
            ...$additional,
        ]);

    }
}
