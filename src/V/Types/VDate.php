<?php

namespace Laramix\Laramix\V\Types;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

class VDate extends BaseType {
    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return 'string';
    }

    public function empty() {
        return '';
    }

    public function parseValueForType($value, BaseType $context) {
        if (!is_int($value)) {
            throw new \Exception('Value is not an integer');
        }
        return $value;
    }


}
