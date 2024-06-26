<?php

namespace Laramix\Laramix\V\Types;

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
    public function toTypeScript(): string
    {
        return 'number';
    }
}
