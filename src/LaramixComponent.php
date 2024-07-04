<?php

namespace Laramix\Laramix;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Laramix\Laramix\V\Types\BaseType;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use ReflectionClass;
use ReflectionFunction;

class LaramixComponent
{
    public function __construct(
        protected string $filePath,
        protected string $name,

    ) {
        $this->name = self::namespaceToName($name);
    }

    public const NAMESPACE = 'LaramixComponent';

    public function exists()
    {
        return file_exists($this->filePath);
    }

    public static function nameToNamespace(string $name)
    {
        return str($name)->replace('$', '＄')->replace('.', '→')->__toString();
    }

    public static function namespaceToName(string $namespace)
    {
        return str($namespace)->replace('＄', '$')->replace('→', '.')->__toString();
    }

    public function getName()
    {
        return $this->name;
    }

    public function hasProps(): bool
    {
        return (bool) $this->compile()['_props'] ?? false;
    }

    public function propsFunction()
    {
        return $this->compile()['_props'] ?? null;
    }

    public function toManifest()
    {
        $compiled = $this->compile();

        return [
            'component' => $this->name,
            'actions' => $compiled['actions'],
            'props' => $this->deriveDefaultProps(),
        ];
    }

    public function deriveDefaultProps(): ?array
    {
        $compiled = $this->compile();
        $props = $compiled['_props'] ?? null;
        if (! $props) {
            return [];
        }

        if (is_a($props, Action::class)) {
            if ($props->responseType) {
                return $props->responseType->empty();
            }
            $props = $props->handler;
        }

        $reflection = match (true) {
            is_a($props, Closure::class) => new ReflectionClosure($props),
            default => null
        };
        if (! $reflection) {
            return null;
        }

        $returns = $reflection->getReturnType();
        if (! $returns) {
            return null;
        }
        $returns = $returns->getName();
        if (is_subclass_of($returns, Validator::class, true)) {
            return app($returns)->defaults();
        }

        return null;
    }

    private static $compiled = [];

    public function compile()
    {
        $cacheName = static::namespaceToName($this->name);
        if (isset(static::$compiled[$cacheName])) {
            return static::$compiled[$cacheName];
        }
        $compiledCompoent = $this->_compile();
        static::$compiled[$cacheName] = $compiledCompoent;

        return $compiledCompoent;
    }

    private function _compile()
    {
        $path = $this->filePath;
        $name = $this->name;
        $existingClasses = collect(get_declared_classes())->toArray();
        try {
            ob_start();

            $__path = $path;

            $items = (static function () use ($__path, $name, $existingClasses) {
                $namespace = self::NAMESPACE.'\\'.self::nameToNamespace($name);
                $contents = '<?php namespace '.$namespace.';';

                $contents .= '
                class Props extends \Laramix\Laramix\LaramixComponentProps {
                    public static function info() {
                    return json_decode(
                    <<<\'EOT\'
                    '.json_encode([
                    'name' => $name,
                    'component' => LaramixComponent::namespaceToName($name),
                    'namespace' => $namespace,
                    'path' => $__path,
                ]).'
                    EOT, true);
                    }

                };';

                $contents .= '?>';

                $contents .= @file_get_contents($__path);
                if (! Storage::disk('local')->exists('laramix')) {
                    Storage::disk('local')->makeDirectory('laramix');
                }

                Storage::put('laramix/'.$name.'.php', $contents);
                $filePath = Storage::path('laramix/'.$name.'.php');
                require $filePath;

                $variables = array_map(function (mixed $variable) {
                    return $variable;
                }, get_defined_vars());

                $classes = collect(get_declared_classes())
                    ->filter(fn ($class) => str($class)->startsWith($namespace))
                    ->reduce(function ($arr, $class) {
                        $arr[$class] = new ReflectionClass($class);

                        return $arr;
                    }, []);

                return compact('variables', 'classes');

            })();

        } finally {
            ob_get_clean();
        }

        $variables = $items['variables'];
        $classes = $items['classes'];

        $props = [
            'component' => $name,
            'props' => [],
            'actions' => [],
            '_actions' => [],
            '_classes' => $classes ?? [],
        ];
        if ($variables['props'] ?? null && ($variables['props'] instanceof Closure || $variables['props'] instanceof Action)) {
            $props['_props'] = $variables['props'];
        }
        foreach ($variables as $key => $value) {
            if ($value instanceof Action) {
                $props['actions'][] = ($value->isInertia() ? '$' : '').$key;
                $props['_actions'][$key] = $value;
            }

            if ($value instanceof Closure && $key !== 'props') {

                $reflection = new ReflectionFunction($value);
                $returns = $reflection->getReturnType();
                //? Could make default not an inertia response? Perhaps configurable.
                $isInertia = ($returns && is_a($returns->getName(), Response::class, true));

                $props['actions'][] = $isInertia ? '$'.$key : $key;
                $props['_actions'][$key] = $value;

            }
        }

        return $props;
    }

    public function handleAction(Request $request, string $action)
    {
        $component = $this->compile();
        $args = $request->input('_args', []);

        if (in_array($action, $component['actions']) || in_array('$'.$action, $component['actions'])) {
            $actionFn = $component['_actions'][$action];
            if ($actionFn instanceof Action) {
                if ($actionFn->middleware) {

                }
                $args = ['input' => $args];
            }
            try {
                return ImplicitlyBoundMethod::call(app(), $actionFn, $args);
            } catch (BindingResolutionException $e) {

                throw new BindingResolutionException('Failed to call route action "'.$this->name.'@'.$action.'": '.$e->getMessage(), 0, $e);
            }
        }

        abort(404);
    }

    public function middlewareFor(string $actionName)
    {
        $component = $this->compile();
        $action = $component['_actions'][$actionName] ?? null;
        if ($action instanceof Action) {
            return $action->middleware;
        }

        return [];
    }

    public function classes()
    {
        return $this->compile()['_classes'];
    }

    public function actions()
    {
        return $this->compile()['_actions'];
    }

    public function props()
    {

        $component = $this->compile();

        unset($component['_actions']);
        unset($component['_classes']);
        if (isset($component['_props'])) {
            if (is_a($component['_props'], Action::class)) {
                $component['props'] = $component['_props'](request()->route()->parameters());
            } else {
                $component['props'] = ImplicitlyBoundMethod::call(app(), $component['_props'], request()->route()->parameters());
                if (is_subclass_of($component['props'], Validator::class)) {
                    $component['props'] = $component['props']->__invoke();
                }
            }
            //$component['props'] =  app()->call($component['_props'], request()->route()->parameters());
            unset($component['_props']);
        }

        return $component;

    }
}
