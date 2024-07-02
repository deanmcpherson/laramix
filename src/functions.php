<?php

namespace Laramix\Laramix;

use Closure;
use Laramix\Laramix\V\Types\BaseType;
use Laramix\Laramix\V\V;

function action(
    Closure $handler,
        ?BaseType $requestType = null,
        ?BaseType $responseType = null,
        ?array $middleware = null
    )
{
    return new Action($handler, $requestType, $responseType, $middleware);
}

function v()
{
    return new V;
}
