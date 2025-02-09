<?php

namespace Laramix\Laramix;

use Closure;
use Vod\Vod\Types\BaseType;

class Action
{
    public function __construct(
        public ?Closure $handler,
        public ?BaseType $requestType = null,
        public ?BaseType $responseType = null,
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
}
