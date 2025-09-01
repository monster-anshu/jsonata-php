<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _EntryCallback
{
    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     * @param mixed $input
     * @param \Monster\JsonataPhp\_Frame $frame
     */
    public function callback($symbol, $input, $frame): void;
}
