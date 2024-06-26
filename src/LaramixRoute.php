<?php

namespace Laramix\Laramix;

use Laramix\Laramix\Data\ExampleDTO;
use Inertia\Inertia;

class LaramixRoute {
    public function __construct(
        protected string $path,
        protected string $name
    ) {
    }

    public function getPath() : string {
        return $this->path;
    }

    public function getName() : string {
        return $this->name;
    }

    public function render() {

       /* $test = v()->string()->parse('test');
        $bool = v()->boolean()->parse(true);
        $arr = v()->array()->parse([1, 2, 3]);


        $validator = v()->object([
                'name' => v()->string(),
                'age' => v()->number(),
                'example' => v()->dto(ExampleDTO::class)->optional(),
                'metadata' => v()->object([
                    'key' => v()->string(),
                    'value' => v()->string(),
                  'items' => v()->object([
                    'tag' => v()->string(),
                    'count' => v()->number()
                ])->array()->optional()
                ])->optional()
            ]);

        return $validator->toTypeScript();
        $res = $validator->parse([
            'name' => 'John',
            'age' => 30,
            'example' => [
                'name' => 'billy',
                'age' => 321,
                'another' => [
                    'id' => 'billy',
                    'increment' => 321,
                ]
            ],
            'metadata' => [
                'key' => 'test',
                'value' => 'test',

            ]
        ]);

        return $res;
        */
        $componentNames = explode('|', $this->name);
        $components = collect($componentNames)->map(function($componentName) {


            return app(Laramix::class)->component($componentName)->props();
        });

        return Inertia::render('Laramix', [
            'components' => $components
        ]);

    }
}
