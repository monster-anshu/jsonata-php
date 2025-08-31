<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixAnd extends _Infix
{
    public function __construct(Parser $parser)
    {
        parent::__construct($parser, "and");
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        return $this;
    }
}
