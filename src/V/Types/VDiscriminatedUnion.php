<?php

namespace Laramix\Laramix\V\Types;

class VDiscriminatedUnion extends BaseType {
    public function toTypeScript(): string
    {
        return 'number';
    }

    public function empty() {
        return '';
    }

    public function parseValueForType($value, BaseType $context) {
        return $value;
    }
}
