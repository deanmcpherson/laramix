<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

class VEnum extends BaseType
{
    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'any';
    }

    protected $default = '';


    public function parseValueForType($value, BaseType $context)
    {
        return $value;
    }
}
