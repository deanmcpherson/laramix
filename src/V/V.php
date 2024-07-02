<?php

namespace Laramix\Laramix\V;

use Laramix\Laramix\V\Types\BaseType;
use Laramix\Laramix\V\Types\VAny;
use Laramix\Laramix\V\Types\VArray;
use Laramix\Laramix\V\Types\VBigInt;
use Laramix\Laramix\V\Types\VBoolean;
use Laramix\Laramix\V\Types\VDTO;
use Laramix\Laramix\V\Types\VEnum;
use Laramix\Laramix\V\Types\VLiteral;
use Laramix\Laramix\V\Types\VNumber;
use Laramix\Laramix\V\Types\VObject;
use Laramix\Laramix\V\Types\VString;
use Spatie\LaravelData\Data;

class V
{
    public function string()
    {
        return new VString();
    }

    public function literal()
    {
        return new VLiteral();
    }

    public function dto(string $className)
    {
        return new VDTO($className);
    }

    public function number()
    {
        return new VNumber();
    }

    public function boolean()
    {
        return new VBoolean();
    }

    public function bigInt()
    {
        return new VBigInt();
    }

    /**
     * @template T
     *
     * @param  $t  extends array<string, BaseType>
     * @return VArray<T>
     */
    public function array($schema = new VAny())
    {
        return new VArray($schema);
    }

    public function any() {
        return new VAny();
    }

    public function infer(mixed $type) {
        if (is_string($type)) {
            return $this->string()->default($type);
        }
        if (is_int($type)) {
            return $this->number()->default($type);
        }
        if (is_bool($type)) {
            return $this->boolean()->default($type);
        }
        if (is_array($type)) {
            //is associative array?
            if (array_keys($type) !== range(0, count($type) - 1)) {
                return $this->inferObject($type);
            }
            return $this->array();
        }
        if (is_object($type)) {
            if (is_subclass_of($type, BaseType::class)) {
                return $type;
            }

            if (is_subclass_of($type, Data::class)) {
                return $this->dto(get_class($type));
            }
            return $this->inferObject($type);
        }
        return $this->any();
    }

    protected function inferObject($object) {
        $inferredObject = [];
        foreach ($object as $key => $value) {
            $inferredObject[$key] = $this->infer($value);
        }
        return $this->object($inferredObject);
    }

    /**
     * @template T
     *
     * @param  $t  extends array<string, BaseType>
     * @return VObject<T>
     */
    public function object(array $schema)
    {
        return new VObject($schema);
    }

    public function enum(...$args)
    {
        return new VEnum(...$args);
    }
}
