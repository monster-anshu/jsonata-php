<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Frame
{
    private array $bindings = [];

    public bool $isParallelCall = false;

    public function __construct(private readonly ?_Frame $frame = null)
    {
    }

    public function bind(string $name, mixed $val): void
    {
        $this->bindings[$name] = $val;
    }

    public function bindFunction(string $name, _JFunction $jFunction): void
    {

        $this->bind($name, $jFunction);
        if ($jFunction->signature instanceof \Monster\JsonataPhp\Signature) {
            $jFunction->signature->setFunctionName($name);
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

        if ($this->frame !== null) {
            return $this->frame->lookup($name);
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

    public function setEvaluateEntryCallback(_EntryCallback $entryCallback): void
    {
        $this->bind("__evaluate_entry", $entryCallback);
    }

    public function setEvaluateExitCallback(_ExitCallback $exitCallback): void
    {
        $this->bind("__evaluate_exit", $exitCallback);
    }
}
