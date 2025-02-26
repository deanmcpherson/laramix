<?php

namespace Laramix\Laramix;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;
use Vod\Vod\Types\BaseType;

class LaramixTypeTransformer implements Transformer
{
    use TransformsTypes;

    public function transform(ReflectionClass $class, string $name): ?TransformedType
    {

        if (is_a($class->getName(), Action::class, true)) {
            $missingSymbols = new MissingSymbolsCollection;

            $reflector = ClassTypeReflector::create($class);

            return TransformedType::create(
                $reflector->getReflectionClass(),
                $reflector->getName(),
                app(Laramix::class)->actionsTypeScript(),
                $missingSymbols
            );
        }

        if (is_subclass_of($class->getName(), LaramixComponentBase::class)) {

            $info = $class->getName()::info();
            $componentName = $info['component'];
            $path = $info['path'];
            /** @var LaramixComponent $component */
            $component = app(Laramix::class)->component($componentName, $path);

            return match (true) {
                is_subclass_of($class->getName(), LaramixComponentProps::class) => $this->generateComponentTypes($class, $component),
                default => null
            };
        }

        return null;
    }

    private function generateComponentTypes(ReflectionClass $class, LaramixComponent $component): ?TransformedType
    {
        $missingSymbols = new MissingSymbolsCollection;

        $reflector = ClassTypeReflector::create($class);

        $actionTypes = $this->generateActionTypes($component, $missingSymbols);
        $propTypes = $this->generatePropTypes($component, $missingSymbols);

        return TransformedType::create(
            $reflector->getReflectionClass(),
            $reflector->getName(),
            "{
                props: $propTypes;
                actions: $actionTypes;
                parameters: any;
                errors: any;
                router: import('@inertiajs/core').Router;
                eager: true|undefined;
             }",
            $missingSymbols
        );
    }

    private function generatePropTypes(LaramixComponent $component, MissingSymbolsCollection $missingSymbols): string
    {
        $propsFunction = $component->propsFunction();

        if ($propsFunction instanceof Action) {
            if ($propsFunction->responseType && is_subclass_of($propsFunction->responseType, BaseType::class, true)) {
                return $propsFunction->responseType->toTypeScript($missingSymbols);
            }
            $propsFunction = $propsFunction->handler;
        }

        if ($propsFunction instanceof Closure) {
            $reflection = new ReflectionMethod($propsFunction, '__invoke');

            return $this->reflectionToTypeScript($reflection, $missingSymbols);
        }

        return 'any';
    }

    private function generateActionTypes(LaramixComponent $component, MissingSymbolsCollection $missingSymbols): string
    {

        $actions = $component->actions();

        $ts = '';
        foreach ($actions as $actionName => $method) {

            if ($method instanceof Action) {
                $inputType = $method->requestType?->toTypeScript($missingSymbols) ?? 'any';

                $ts .= "$actionName: Laramix.ActionFn<$inputType, ".($method->responseType?->toTypeScript($missingSymbols) ?? 'any').">;\n";

                continue;
            }

            $actionReflector = new ReflectionFunction($method);

            $argument = '';

            foreach ($actionReflector->getParameters() as $parameter) {
                $type = $this->reflectionToType($parameter, $missingSymbols);
                $argument .= $parameter->getName().': '.$this->typeToTypeScript($type, $missingSymbols).';';
            }

            if ($argument) {
                $argument = '{'.$argument.'}';
            } else {
                $argument = 'any';
            }

            $ts .= "$actionName: Laramix.ActionFn<$argument, any>;\n";
        }

        return "{ $ts }";
    }
}
