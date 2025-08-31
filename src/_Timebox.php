<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

/**
 * Configure max runtime / max recursion depth.
 * See Frame::setRuntimeBounds - usually not used directly
 */
class _Timebox
{
    private readonly float $time;
     // start time in ms
    private int $depth = 0;

    public function __construct(_Frame $frame, private readonly int $timeout = 5000, private readonly int $maxDepth = 100)
    {
        $this->time = (int) (microtime(true) * 1000);

        // register callbacks
        $frame->setEvaluateEntryCallback(
            new class ($this) implements _EntryCallback {
                public function __construct(private readonly _Timebox $timebox)
                {
                }

                public function callback(Symbol $symbol, mixed $input, _Frame $frame): void
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
                public function __construct(private readonly _Timebox $timebox)
                {
                }

                public function callback(Symbol $symbol, mixed $input, _Frame $frame, mixed $result): void
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
