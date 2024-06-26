<?php

namespace Laramix\Laramix\V\Types;

class VDate extends BaseType {
    public function toTypeScript(): string
    {
        return 'string';
    }

    public function parseValueForType($value, BaseType $context) {
        if (!is_int($value)) {
            throw new \Exception('Value is not an integer');
        }
        return $value;
    }


}
