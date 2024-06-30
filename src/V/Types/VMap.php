<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

class VMap extends BaseType
{
    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'any';
    }

    public function parseValueForType($value, BaseType $context)
    {
        return $value;
    }
}
