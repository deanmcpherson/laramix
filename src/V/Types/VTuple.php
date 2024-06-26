<?php

namespace Laramix\Laramix\V\Types;

class VTuple extends BaseType {
    public function toTypeScript(): string
    {
        return 'any';
    }

    public function parseValueForType($value, BaseType $context) {
        return $value;
    }
}
