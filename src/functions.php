<?php

namespace Laramix\Laramix;

use Laramix\Laramix\V\Types\BaseType;
use Closure;
use Laramix\Laramix\V\V;

function action(Closure $handler, ?BaseType $requestType = null, ?BaseType $responseType = null) {
   return new Action($handler, $requestType, $responseType);
}

function v() {
    return new V;
}
