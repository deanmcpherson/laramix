<?php

namespace Laramix\Laramix\V\Types;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

use Spatie\TypeScriptTransformer\Actions\TranspileTypeToTypeScriptAction;
use phpDocumentor\Reflection\Type;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Spatie\LaravelTypeScriptTransformer\Transformers\DtoTransformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

/**
 * @template T
 *  extends BaseType<T>
 */
class VDTO extends BaseType {
    use TransformsTypes;

    /**
     * @param class-string<T> $className
     */
    public function __construct(
        public string $className
    )
    {
        if (!class_exists($this->className)) {
            throw new \Exception('Class does not exist');
        }
        if (!is_subclass_of($this->className, Data::class)) {
            throw new \Exception('Class does not extend LaravelData');
        }
    }

    public function empty() {
        return $this->className::empty();
    }

    public function toTypeScript(MissingSymbolsCollection $collection): string
    {

        $dtoTransformer = new DtoTransformer(
            TypeScriptTransformerConfig::create(
               // config('typescript-transformer')
            )
        );
        $reflection = new ReflectionClass($this->className);
       // dd($dtoTransformer->transform($reflection, $this->className));
        $transformed = $dtoTransformer->transform($reflection, $this->className);

        foreach( $transformed->missingSymbols->all() as $symbol)
        {
            $collection->add($symbol);
        }

        return $transformed->transformed;

    }

    public function parseValueForType($value, BaseType $context) {
        try {
        return $this->className::from($value);
        } catch (\Exception $e) {
            $context->addIssue(0, $this, $e->getMessage());
        }
    }
}
