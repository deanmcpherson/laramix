<?php

namespace Laramix\Laramix\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laramix\Laramix\Laramix
 */
class Laramix extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laramix\Laramix\Laramix::class;
    }
}
