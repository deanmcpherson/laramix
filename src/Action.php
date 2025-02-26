<?php

namespace Laramix\Laramix;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Serializers\Native;
use Vod\Vod\Types\BaseType;

class Action
{
    public function __construct(
        public Closure|SerializableClosure|Native|null $handler = null,
        public ?BaseType $requestType = null,
        public ?BaseType $responseType = null,
        /**
         * @var array<string>
         */
        public ?array $middleware = null
    ) {}

    public function withMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function returns(BaseType $responseType): self
    {
        $this->responseType = $responseType;

        return $this;
    }

    public function expects(BaseType $requestType): self
    {
        $this->requestType = $requestType;

        return $this;
    }

    public function handler(Closure|SerializableClosure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

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

        return $this->responseType->parse($responsePayload);

    }
}
