<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

/**
 * Configure max runtime / max recursion depth.
 * See Frame::setRuntimeBounds - usually not used directly
 */
class _Timebox
{
    /**
     * @readonly
     * @var int
     */
    private $timeout = 5000;
    /**
     * @readonly
     * @var int
     */
    private $maxDepth = 100;
    /**
     * @readonly
     * @var float
     */
    private $time;
     // start time in ms
    /**
     * @var int
     */
    private $depth = 0;

    public function __construct(_Frame $frame, int $timeout = 5000, int $maxDepth = 100)
    {
        $this->timeout = $timeout;
        $this->maxDepth = $maxDepth;
        $this->time = (int) (microtime(true) * 1000);

        // register callbacks
        $frame->setEvaluateEntryCallback(
            new class ($this) implements _EntryCallback {
                /**
                 * @readonly
                 * @var \Monster\JsonataPhp\_Timebox
                 */
                private $timebox;
                public function __construct(_Timebox $timebox)
                {
                    $this->timebox = $timebox;
                }

                /**
                 * @param \Monster\JsonataPhp\Symbol $symbol
                 * @param mixed $input
                 * @param \Monster\JsonataPhp\_Frame $frame
                 */
                public function callback($symbol, $input, $frame): void
                {
                    if ($frame->isParallelCall) {
                        return;
                    }

                    $this->timebox->incrementDepth();
                    $this->timebox->checkRunnaway();
                }
            }
        );

        $expr->setEvaluateExitCallback(
            new class ($this) implements _ExitCallback {
                /**
                 * @readonly
                 * @var \Monster\JsonataPhp\_Timebox
                 */
                private $timebox;
                public function __construct(_Timebox $timebox)
                {
                    $this->timebox = $timebox;
                }

                /**
                 * @param \Monster\JsonataPhp\Symbol $symbol
                 * @param mixed $input
                 * @param \Monster\JsonataPhp\_Frame $frame
                 * @param mixed $result
                 */
                public function callback($symbol, $input, $frame, $result): void
                {
                    if ($frame->isParallelCall) {
                        return;
                    }

                    $this->timebox->decrementDepth();
                    $this->timebox->checkRunnaway();
                }
            }
        );
    }

    public function incrementDepth(): void
    {
        ++$this->depth;
    }

    public function decrementDepth(): void
    {
        --$this->depth;
    }

    public function checkRunnaway(): void
    {
        if ($this->depth > $this->maxDepth) {
            throw new JException(
                sprintf('Stack overflow error: Check for non-terminating recursive function. Consider rewriting as tail-recursive. Depth=%d max=%d', $this->depth, $this->maxDepth),
                -1
            );
        }

        if ((int) (microtime(true) * 1000) - $this->time > $this->timeout) {
            throw new JException(
                "Expression evaluation timeout: Check for infinite loop",
                -1
            );
        }
    }
}
