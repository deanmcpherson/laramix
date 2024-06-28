<?php

namespace Laramix\Laramix\V\Types;

use Closure;

/**
 * @template T
 * */
abstract class BaseType {

    protected array $rules = [];
    protected array $after = [];
    protected array $issues = [];
    protected bool $optional = false;

    abstract protected function parseValueForType(mixed $value, BaseType $context);
    public function empty() {
        return null;
    }
    /**
     *
     * @param mixed $value
     * @return T
     */
    public function parse(mixed $value) {

        $results = $this->safeParse($value);
        if (!$results['ok']) {
            $message = '';
            foreach ($results['errors'] as $issue) {
                [$code, $source, $msg] = $issue;
                $message .= $msg . PHP_EOL;
            }
            throw new \Exception($message);
        }
        return $results['value'];
    }

    /**
     *
     * @return VArray<T>
     */
    public function array() : VArray {
        return new VArray($this);
    }


    abstract public function toTypeScript(): string;
    /**
     *
     * @param mixed $value
     * @return T
     */
    public function safeParse(mixed $value) {
        $this->issues = [];
        $value = $this->parseValueForType($value, $this);

        foreach ($this->after as $after) {
            [$method, $closure] = $after;
            $value = $closure($value);
        }
        if ($this->issues) {
            $issues = $this->issues;
            $this->issues = [];
            return [
                'ok' => false,
                'errors' => $this->summarizeIssues($issues),
            ];
        }
        return [
            'ok' => true,
            'value' => $value
        ];
    }

    public function summarizeIssues(array $issues) {
        $summarized = [];
        foreach ($issues as $issue) {
            [$code, $source, $message] = $issue;
            $summarized[] = $message;
        }
        return implode("\n",$summarized);
    }

    public function isOptional(): bool {
        return $this->optional;
    }

    public function optional() {
        $this->optional = true;
        return $this;
    }

    public function required() {
        $this->optional = false;
        return $this;
    }


    protected function addIssue(int $issueCode,  BaseType $source, string $message) {
        $this->issues[] = [
            $issueCode,
            $source,
            $message
        ];
    }

    public function refine(Closure $refiner) {
        $this->after[] = ['refine', $refiner];
        return $this;
    }

    public function transform(Closure $transformer) {
        $this->after[] = ['transform', $transformer];
        return $this;
    }

}
