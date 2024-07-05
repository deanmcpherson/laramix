<?php

namespace Laramix\Laramix;

use Closure;
use Laramix\Laramix\V\Types\BaseType;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;

class LaramixTypeTransformer implements Transformer
{
    use TransformsTypes;

    public function transform(ReflectionClass $class, string $name): ?TransformedType
    {

        if (is_subclass_of($class->getName(), Validator::class, true)) {
            $reflector = ClassTypeReflector::create($class);
            $missingSymbols = new MissingSymbolsCollection();

            return TransformedType::create(
                $reflector->getReflectionClass(),
                $reflector->getName(),
                app($class->getName())->v()->toTypeScript($missingSymbols),
                $missingSymbols
            );

        }

        if (is_a($class->getName(), Action::class, true)) {
            $missingSymbols = new MissingSymbolsCollection();

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
        $missingSymbols = new MissingSymbolsCollection();

        $reflector = ClassTypeReflector::create($class);

        $actionTypes = $this->generateActionTypes($component, $missingSymbols);
        $propTypes = $this->generatePropTypes($component, $missingSymbols);

        return TransformedType::create(
            $reflector->getReflectionClass(),
            $reflector->getName(),
            "{
                props: $propTypes;
                actions: $actionTypes;
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
                $optional = $inputType === 'any' || $method->requestType?->isOptional() ? '?' : '';
                $ts .= "$actionName:
                {
                    call: (input{$optional}: ".($method->requestType?->toTypeScript($missingSymbols) ?? 'any').') => Promise<{data:'.($method->responseType?->toTypeScript($missingSymbols) ?? 'any')."}>;
                    visit: (input{$optional}: ".($method->requestType?->toTypeScript($missingSymbols) ?? 'any').", options?: Laramix.VisitOptions) => void;
                }\n";

                continue;
            }

            $actionReflector = new ReflectionFunction($method);

            $argument = '';

            foreach ($actionReflector->getParameters() as $parameter) {
                $type = $this->reflectionToType($parameter, $missingSymbols);
                $argument .= $parameter->getName().': '.$this->typeToTypeScript($type, $missingSymbols).';';
            }

            if ($argument) {
                $argument = 'payload: {'.$argument.'},';
            }
            $ts .= "$actionName: {
                call: ($argument) => Promise<any>;
                visit: ($argument options?: Laramix.VisitOptions) => void;
            }\n";
        }

        return "{ $ts }";
    }
}
