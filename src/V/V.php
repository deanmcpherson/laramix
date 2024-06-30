<?php

namespace Laramix\Laramix\V;

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
