<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Frame
{
    /**
     * @readonly
     * @var \Monster\JsonataPhp\_Frame|null
     */
    private $frame;
    /**
     * @var mixed[]
     */
    private $bindings = [];

    /**
     * @var bool
     */
    public $isParallelCall = false;

    public function __construct(?_Frame $frame = null)
    {
        $this->frame = $frame;
    }

    /**
     * @param string $name
     * @param mixed $val
     */
    public function bind($name, $val): void
    {
        $this->bindings[$name] = $val;
    }

    /**
     * @param string $name
     * @param \Monster\JsonataPhp\_JFunction $jFunction
     */
    public function bindFunction($name, $jFunction): void
    {

        $this->bind($name, $jFunction);
        if ($jFunction->signature instanceof \Monster\JsonataPhp\Signature) {
            $jFunction->signature->setFunctionName($name);
        }
    }

    /**
     * @param string $name
     * @param callable $lambda
     */
    public function bindLambda($name, $lambda): void
    {
        $this->bind($name, $lambda);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function lookup($name)
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
    public function setRuntimeBounds($timeout, $maxRecursionDepth): void
    {
        new _Timebox($this, $timeout, $maxRecursionDepth);
    }

    /**
     * @param \Monster\JsonataPhp\_EntryCallback $entryCallback
     */
    public function setEvaluateEntryCallback($entryCallback): void
    {
        $this->bind("__evaluate_entry", $entryCallback);
    }

    /**
     * @param \Monster\JsonataPhp\_ExitCallback $exitCallback
     */
    public function setEvaluateExitCallback($exitCallback): void
    {
        $this->bind("__evaluate_exit", $exitCallback);
    }
}
