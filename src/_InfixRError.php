<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixRError extends _Infix
{
    public function __construct(Parser $parser)
    {
        parent::__construct($parser, "(error)", 10);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $symbol): Symbol
    {
        throw new \Exception("TODO");
    }
}
