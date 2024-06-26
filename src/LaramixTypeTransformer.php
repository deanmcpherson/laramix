<?php

namespace Laramix\Laramix;

use phpDocumentor\Reflection\Type;
use ReflectionClass;
use ReflectionFunction;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;

class LaramixTypeTransformer implements Transformer {
    use TransformsTypes;

    public function transform(ReflectionClass $class, string $name): ?TransformedType
    {


        if (is_subclass_of($class->getName(), LaramixComponentBase::class)) {

            $file = $class->getFileName();
            $info = $class->getName()::info();
            $componentName = $info['component'];
            $path = $info['path'];
            /** @var LaramixComponent $component */
            $component = app(Laramix::class)->component($componentName, $path);

            return match(true) {
               is_subclass_of($class->getName(), LaramixComponentActions::class) => $this->generateActionTypes($class, $component),
               default => null
            };
        }
        return null;
    }

    private function generateActionTypes(ReflectionClass $class, LaramixComponent $component): ?TransformedType {

        $reflector = ClassTypeReflector::create($class);
        $missingSymbols = new MissingSymbolsCollection();
        $actions =$component->actions();


        $ts = '';
        foreach ($actions as $actionName => $method) {

            if ($method instanceof Action) {
                $ts .= "$actionName: (input: " . ($method->requestValidation->toTypeScript() ?? 'any') . ') => ' . ($method->responseValidation?->toTypeScript() ?? 'any') . ";\n";
                continue;
            }

            $actionReflector = new ReflectionFunction($method);


            $argument = '';

            foreach ($actionReflector->getParameters() as $parameter) {
                $type = $this->reflectionToType($parameter, $missingSymbols);
                $argument .= $parameter->getName() . ': ' . $this->typeToTypeScript($type, $missingSymbols). ';';
            }

             if ($argument) {
                $argument = 'payload: {' . $argument . '}';
            }
            $ts .= "$actionName: ($argument) => void;\n";
        }

        return TransformedType::create(
            $reflector->getReflectionClass(),
            $reflector->getName(),
             "{ $ts }",
            $missingSymbols
        );
    }
}
