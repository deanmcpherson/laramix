<?php

namespace Laramix\Laramix\V\Types;

use Closure;
use Illuminate\Support\Facades\Validator;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * @template T
 * */
abstract class BaseType
{
    protected array $rules = [];

    protected array $after = [];

    protected array $issues = [];

    protected $default = null;

    protected bool $optional = false;

    abstract protected function parseValueForType(mixed $value, BaseType $context);

    public function empty()
    {
        return is_null($this->default) ? null : $this->parse($this->default);
    }

    /**
     * @return T
     */
    public function parse(mixed $value)
    {

        $results = $this->safeParse($value);
        if (! $results['ok']) {
            $message = '';
            foreach ($results['issues'] as $issue) {
                [$code, $source, $msg] = $issue;
                $message .= $msg.PHP_EOL;
            }
            throw new \Exception($message);
        }

        return $results['value'];
    }

    public function default(mixed $value)
    {
        $this->default = $value;

        return $this;
    }

    /**
     * @return VArray<T>
     */
    public function array(): VArray
    {
        return new VArray($this);
    }

    abstract public function toTypeScript(MissingSymbolsCollection $collection): string;

    /**
     * @return T
     */
    public function safeParse(mixed $value, string $label = 'value')
    {
        $this->issues = [];
        try {
            $value = $this->parseValueForType($value, $this);
        } catch (\Exception $e) {
            $this->addIssue(0, $this, $e->getMessage());
        }
        if ($this->rules) {
            $rules = $this->rules;
            if ($this->isOptional()) {
                $rules[] = 'nullable';
            }
            $validator = Validator::make([$label => $value], [$label => $rules]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {

                    $this->addIssue(0, $this, $error);
                }
            }
        }

        foreach ($this->after as $after) {
            [$method, $closure] = $after;
            $value = $closure($value);
        }
        // @phpstan-ignore-next-line
        if ($this->issues) {
            $issues = $this->issues;
            $this->issues = [];

            return [
                'ok' => false,
                'errors' => $this->summarizeIssues($issues),
                'issues' => $issues,
            ];
        }

        return [
            'ok' => true,
            'value' => $value,
        ];
    }

    public function rules($rules)
    {

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        //concat all rules
        $this->rules = array_merge($this->rules, $rules);

        return $this;
    }

    public function summarizeIssues(array $issues)
    {
        $summarized = [];
        foreach ($issues as $issue) {
            [$code, $source, $message] = $issue;
            $summarized[] = $message;
        }

        return implode("\n", $summarized);
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function optional()
    {
        $this->optional = true;

        return $this;
    }

    public function required()
    {
        $this->optional = false;

        return $this;
    }

    protected function addIssue(int $issueCode, BaseType $source, string $message)
    {
        $this->issues[] = [
            $issueCode,
            $source,
            $message,
        ];
    }

    public function refine(Closure $refiner)
    {
        $this->after[] = ['refine', $refiner];

        return $this;
    }

    public function transform(Closure $transformer)
    {
        $this->after[] = ['transform', $transformer];

        return $this;
    }
}
