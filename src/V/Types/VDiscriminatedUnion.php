<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

class VDiscriminatedUnion extends BaseType
{
    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'number';
    }

    public function empty()
    {
        return '';
    }

    public function parseValueForType($value, BaseType $context)
    {
        return $value;
    }
}
