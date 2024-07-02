<?php

namespace Laramix\Laramix;

use Inertia\Inertia;

class LaramixRoute
{
    public function __construct(
        public string $path,
        public string $name,
        public array $middleware = [],
        public bool $isLayout = false,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
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

        return Inertia::render('Laramix', [
            'components' => $this->components()->map(fn ($component) => $component->props()),
            'parameters' => request()->route()->parameters(),
        ]);

    }
}
