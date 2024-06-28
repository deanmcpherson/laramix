<?php

namespace Laramix\Laramix\V\Types;

/**
 * @extends BaseType<string>
 * */
class VString extends BaseType {
    public function parseValueForType($value, BaseType $context) {
        if (!is_string($value)) {
            throw new \Exception('Value is not a string');
        }
        return (string) $value;
    }

    public function email(): self {
        return $this;
    }

    public function empty() {
        return '';
    }

    public function toTypeScript(): string
    {
        return 'string';
    }
}
