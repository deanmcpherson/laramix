<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @extends BaseType<int>
 * */
class VBigInt extends BaseType
{
    public function parseValueForType($value, BaseType $context)
    {
        if (! is_int($value)) {
            throw new \Exception('Value is not an integer');
        }

        return $value;
    }

    protected $default = 0;

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'number';
    }
}
