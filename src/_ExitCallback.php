<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;


interface _ExitCallback
{
    public function callback(Symbol $expr, mixed $input, _Frame $environment, mixed $result): void;
}