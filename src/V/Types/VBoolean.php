<?php

namespace Laramix\Laramix\V\Types;

/**
 * @extends BaseType<bool>
 * */
class VBoolean extends BaseType {
    public function parseValueForType($value, BaseType $context) {
        if (!is_bool($value)) {
            throw new \Exception('Value is not a boolean');
        }
        return (bool) $value;
    }

    public function toTypeScript(): string
    {
        return 'boolean';
    }
}
