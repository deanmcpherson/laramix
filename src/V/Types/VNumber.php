<?php

namespace Laramix\Laramix\V\Types;

/**
 * @extends BaseType<float>
 * */
class VNumber extends BaseType {

    public function toTypeScript(): string
    {
        return 'number';
    }

    public function empty() {
        return 0;
    }

    public function parseValueForType($value, BaseType $context) {
        if (!is_numeric($value)) {
            throw new \Exception('Value is not a number');
        }
        return (float) $value;
    }
}
