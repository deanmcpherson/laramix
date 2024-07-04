<?php

namespace Laramix\Laramix;

use Closure;
use Inertia\Response;
use Laramix\Laramix\V\Types\BaseType;
use ReflectionFunction;

class Action
{
    public function __construct(
        public ?Closure $handler,
        public ?BaseType $requestType = null,
        public ?BaseType $responseType = null,
        public ?bool $isInertia = false,
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

        $responsePayload = is_scalar($responsePayload) ? $responsePayload : json_decode(response($responsePayload)->getContent(), true);

        return $this->responseType->parse($responsePayload);

    }

    public function isInertia()
    {

        if ($this->isInertia) {
            return true;
        }

        if ($this->isInertia === false) {
            return false;
        }

        if ($this->responseType) {
            return false;
        }

        if (!$this->handler) {
            return false;
        }
        $reflection = new ReflectionFunction($this->handler);
        $returnType = $reflection->getReturnType();
        if (!$returnType || is_a($returnType->getName(), Response::class, true)) {
            return true;
        }

        return false;
    }
}
