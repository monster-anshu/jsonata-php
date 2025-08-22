<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

interface _EntryCallback
{
    public function callback(Symbol $expr, mixed $input, _Frame $environment): void;
}
