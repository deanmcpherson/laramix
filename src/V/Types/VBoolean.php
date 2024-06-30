<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @extends BaseType<bool>
 * */
class VBoolean extends BaseType
{
    public function parseValueForType($value, BaseType $context)
    {
        if (! is_bool($value)) {
            throw new \Exception('Value is not a boolean');
        }

        return (bool) $value;
    }

    public function empty()
    {
        return false;
    }

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'boolean';
    }
}
