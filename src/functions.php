<?php

namespace Laramix\Laramix;

use Laramix\Laramix\V\Types\BaseType;
use Closure;
use Laramix\Laramix\V\V;

function action(Closure $handler, ?BaseType $requestValidation = null, ?BaseType $responseValidation = null) {
   return new Action($handler, $requestValidation, $responseValidation);
}

function v() {
    return new V;
}
