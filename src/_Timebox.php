<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

/**
 * Configure max runtime / max recursion depth.
 * See Frame::setRuntimeBounds - usually not used directly
 */
class _Timebox
{
    private readonly float $time; // start time in ms
    private int $depth = 0;

    public function __construct(_Frame $expr, private readonly int $timeout = 5000, private readonly int $maxDepth = 100)
    {
        $this->time = (int) (microtime(true) * 1000);

        // register callbacks
        $expr->setEvaluateEntryCallback(
            new class ($this) implements _EntryCallback {
            public function __construct(private readonly _Timebox $tb)
            {
            }

            public function callback(Symbol $expr, mixed $input, _Frame $environment): void
            {
                if ($environment->isParallelCall) {
                    return;
                }
                $this->tb->incrementDepth();
                $this->tb->checkRunnaway();
            }
            }
        );

        $expr->setEvaluateExitCallback(
            new class ($this) implements _ExitCallback {
            public function __construct(private readonly _Timebox $tb)
            {
            }

            public function callback(Symbol $expr, mixed $input, _Frame $environment, mixed $result): void
            {
                if ($environment->isParallelCall) {
                    return;
                }
                $this->tb->decrementDepth();
                $this->tb->checkRunnaway();
            }
            }
        );
    }

    public function incrementDepth(): void
    {
        $this->depth++;
    }

    public function decrementDepth(): void
    {
        $this->depth--;
    }

    public function checkRunnaway(): void
    {
        if ($this->depth > $this->maxDepth) {
            throw new JException(
                "Stack overflow error: Check for non-terminating recursive function. Consider rewriting as tail-recursive. Depth={$this->depth} max={$this->maxDepth}",
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