<?php

namespace Laramix\Laramix;

use Closure;
use Inertia\Response;
use Laramix\Laramix\V\Types\BaseType;

class Action
{
    public function __construct(
        public ?Closure $handler,
        public ?BaseType $requestType = null,
        public mixed $responseType = null,
        /**
         * @var array<string>
         */
        public ?array $middleware = null
    ) {}

    public function __invoke($input)
    {
        if (! $this->handler) {
            abort(500, 'No handler provided for action');
        }
        $parsedInput = false;
        if ($this->requestType) {
            $parsedInput = $this->requestType->safeParse($input);
            if (! $parsedInput['ok']) {
                abort(422, $parsedInput['errors']);
            }
        }
        $responsePayload = ImplicitlyBoundMethod::call(app(), $this->handler, $parsedInput ? $parsedInput['value'] : $input);

        if ($this->responseType === null) {
            return $responsePayload;
        }

        if ($this->responseType instanceof BaseType) {

            $responsePayload = is_scalar($responsePayload) ? $responsePayload : json_decode(response($responsePayload)->getContent(), true);

            return $this->responseType->parse($responsePayload);
        }

        if (class_exists($this->responseType) && ! is_a($responsePayload, ! $this->responseType, true)) {
            abort(500, 'Response type mismatch, expected '.$this->responseType);
        }

        return $responsePayload;
    }

    public function isInertia()
    {
        if (is_a($this->responseType, Response::class, true)) {
            return true;
        }

        return false;
    }
}
