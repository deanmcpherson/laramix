<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @template T
 *
 * @extends BaseType<array<int, T>>
 * */
class VArray extends BaseType
{
    public function __construct(protected BaseType $type = new VAny()) {}

    public function empty()
    {
        return [];
    }

    public function parseValueForType($value, BaseType $context)
    {
        if (! is_array($value)) {
            return $context->addIssue(0, $this, 'Not an array');
        }
        // check if array is associative
        if (array_keys($value) !== range(0, count($value) - 1)) {
            return $context->addIssue(0, $this, 'An associative array');
        }

        $parsedValue = [];
        foreach ($value as $v) {
            $parsedValue[] = $this->type->parseValueForType($v, $context);
        }

        return $value;
    }

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        return $this->type->toTypeScript($collection).'[]';
    }
}
