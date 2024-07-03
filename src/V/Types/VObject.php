<?php

namespace Laramix\Laramix\V\Types;

use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @extends BaseType<mixed>
 * */
class VObject extends BaseType
{
    /**
     * @param  array<string, BaseType<mixed>>  $schema
     * */
    public function __construct(protected array $schema) {}

    public function empty()
    {
        $empty = [];
        foreach ($this->schema as $key => $type) {
            if ($type->isOptional()) {
                $empty[$key] = null;

                continue;
            }
            $empty[$key] = $type->empty();
        }

        return $empty;
    }

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {
        $schema = [];
        foreach ($this->schema as $key => $type) {
            $part = "$key: {$type->toTypeScript($collection)}";
            if ($type->isOptional()) {
                $part .= '|undefined|null';
            }
            $schema[] = $part;
        }

        return '{'.implode(', ', $schema).'}';
    }

    public function parseValueForType($value, BaseType $context)
    {

        if (! is_array($value)) {
            $context->addIssue(0, $this, 'Not an array');

            return;
        }

        foreach ($this->schema as $key => $type) {
            if (! is_string($key)) {
                throw new \Exception('Keys must be strings');
            }
            if (! ($type instanceof BaseType)) {
                throw new \Exception('Schema values inherit from the BaseType');
            }
        }
        $parsedValue = [];
        foreach ($this->schema as $key => $type) {
            if (! array_key_exists($key, $value)) {
                if (! $type->isOptional()) {
                    $context->addIssue(0, $this, "Required object key  \"$key\" not found");
                }

                continue;
            }
            $results = $type->safeParse($value[$key], $key);
            if (! $results['ok']) {

                foreach ($results['issues'] as $issue) {

                    $context->addIssue(0, $this, $issue[2]);

                }
            }
            $parsedValue[$key] = $results['value'] ?? null;
        }

        return $parsedValue;
    }
}
