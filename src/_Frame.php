<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Frame
{
    private array $bindings = [];
    public bool $isParallelCall = false;

    public function __construct(private readonly ?_Frame $parent = null)
    {
    }

    public function bind(string $name, mixed $val): void
    {
        $this->bindings[$name] = $val;
    }

    public function bindFunction(string $name, _JFunction $function): void
    {

        $this->bind($name, $function);
        if ($function->signature !== null) {
            $function->signature->setFunctionName($name);
        }
    }

    public function bindLambda(string $name, callable $lambda): void
    {
        $this->bind($name, $lambda);
    }

    public function lookup(string $name): mixed
    {
        // Important: if we have a null value, return it
        if (array_key_exists($name, $this->bindings)) {
            return $this->bindings[$name];
        }

        if ($this->parent !== null) {
            return $this->parent->lookup($name);
        }
        return null;
    }

    /**
     * Sets the runtime bounds for this environment
     *
     * @param int $timeout Timeout in millis
     * @param int $maxRecursionDepth Max recursion depth
     */
    public function setRuntimeBounds(int $timeout, int $maxRecursionDepth): void
    {
        new _Timebox($this, $timeout, $maxRecursionDepth);
    }

    public function setEvaluateEntryCallback(_EntryCallback $cb): void
    {
        $this->bind("__evaluate_entry", $cb);
    }

    public function setEvaluateExitCallback(_ExitCallback $cb): void
    {
        $this->bind("__evaluate_exit", $cb);
    }
}
