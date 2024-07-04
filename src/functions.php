<?php

namespace Laramix\Laramix;

use Closure;
use Laramix\Laramix\V\Types\BaseType;
use Laramix\Laramix\V\V;

function action(
    ?Closure $handler = null,
    ?BaseType $requestType = null,
    ?BaseType $responseType = null,
    ?bool $isInertia = null,
    ?array $middleware = null
) {
    return new Action(
        handler: $handler,
        requestType: $requestType,
        responseType: $responseType,
        middleware: $middleware,
        isInertia: $isInertia
    );
}

function v()
{
    return new V;
}
