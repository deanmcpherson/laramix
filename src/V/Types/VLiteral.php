<?php

namespace Laramix\Laramix\V\Types;

class VLiteral extends BaseType {
    public function toTypeScript(): string
    {
        return 'any';
    }

    public function empty() {
        return '';
    }

    public function parseValueForType($value, BaseType $context) {
        return $value;
    }
}
