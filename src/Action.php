<?php

namespace Laramix\Laramix;

use Laramix\Laramix\V\Types\BaseType;
use Closure;
class Action {
    public function __construct(
        public Closure $handler,
        public ?BaseType $requestValidation = null,
        public ?BaseType $responseValidation = null
    )
    {

    }

    public function __invoke($input)
    {
        if ($this->requestValidation) {
            $parsedInput = $this->requestValidation->safeParse($input);

            if (!$parsedInput['ok']) {
                abort(422, $parsedInput['errors']);
            }
        }
        $responsePayload = ImplicitlyBoundMethod::call(app(), $this->handler, $input);
        if ($this->responseValidation) {
            $responsePayload = json_decode(response($responsePayload)->getContent(), true);
            return $this->responseValidation->parse($responsePayload);
        }
        return $responsePayload;
    }

    public function isInertia() {
        return !$this->responseValidation;
    }
}
