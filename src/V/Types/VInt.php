<?php

namespace Laramix\Laramix\V\Types;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @extends BaseType<int>
 * */
class VNumber extends BaseType {
    public function parseValueForType($value) {
        if (!is_numeric($value)) {
            throw new \Exception('Value is not a number');
        }
        return (int) $value;
    }

    public function empty() {
        return 0;
    }

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'number';
    }
}
