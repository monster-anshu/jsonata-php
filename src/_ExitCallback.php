<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _ExitCallback
{
    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     * @param mixed $input
     * @param \Monster\JsonataPhp\_Frame $frame
     * @param mixed $result
     */
    public function callback($symbol, $input, $frame, $result): void;
}
