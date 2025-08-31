<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _EntryCallback
{
    public function callback(Symbol $symbol, mixed $input, _Frame $frame): void;
}
