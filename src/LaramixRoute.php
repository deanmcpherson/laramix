<?php

namespace Laramix\Laramix;

use Inertia\Inertia;

class LaramixRoute
{
    public function __construct(
        protected string $path,
        protected string $name
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
        });
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
