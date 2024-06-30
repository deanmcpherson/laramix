<?php

namespace Laramix\Laramix;

use Closure;
use Laramix\Laramix\V\Types\BaseType;

class Action
{
    public function __construct(
        public Closure $handler,
        public ?BaseType $requestType = null,
        public ?BaseType $responseType = null
    ) {}

    public function __invoke($input)
    {
        $parsedInput = false;
        if ($this->requestType) {
            $parsedInput = $this->requestType->safeParse($input);

            if (! $parsedInput['ok']) {
                abort(422, $parsedInput['errors']);
            }
        }
        $responsePayload = ImplicitlyBoundMethod::call(app(), $this->handler, $parsedInput ? $parsedInput['value'] : $input);
        if ($this->responseType) {
            $responsePayload = json_decode(response($responsePayload)->getContent(), true);

            return $this->responseType->parse($responsePayload);
        }

        return $responsePayload;
    }

    public function isInertia()
    {
        return ! $this->responseType;
    }
}
