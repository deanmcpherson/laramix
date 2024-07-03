<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @extends BaseType<float>
 * */
class VNumber extends BaseType
{
    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'number';
    }

    protected $default = 0;

    public function parseValueForType($value, BaseType $context)
    {
        if (! is_numeric($value)) {
            throw new \Exception('Value is not a number');
        }

        return (float) $value;
    }
}
