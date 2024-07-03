<?php

namespace Laramix\Laramix;

use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;

class LaramixTypeCollector extends DefaultCollector
{
    public function getTransformedType(ReflectionClass $class): ?TransformedType
    {

        if ($class->getName() === Action::class || str($class->getName())->startsWith(LaramixComponent::NAMESPACE.'\\')) {
            $reflector = ClassTypeReflector::create($class);

            $transformedType = $reflector->getType()
                ? $this->resolveAlreadyTransformedType($reflector)
                : $this->resolveTypeViaTransformer($reflector);

            if ($reflector->isInline()) {
                $transformedType->name = null;
                $transformedType->isInline = true;
            }

            return $transformedType;

        }

        return null;
    }
}
