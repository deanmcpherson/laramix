<?php

namespace Laramix\Laramix;

use Closure;
use Vod\Vod\Types\BaseType;
use Vod\Vod\V;

function action(
    ?Closure $handler = null,
    BaseType|string|null $requestType = null,
    BaseType|string|null $responseType = null,
    ?array $middleware = null
) {
    return new Action(
        handler: $handler,
        requestType: $requestType,
        responseType: $responseType,
        middleware: $middleware,

    );
}

function v()
{
    return new V;
}
