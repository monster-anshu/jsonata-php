<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _ExitCallback
{
    public function callback(Symbol $symbol, mixed $input, _Frame $frame, mixed $result): void;
}
