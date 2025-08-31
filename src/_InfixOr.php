<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixOr extends _Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "or");
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        return $this;
    }
}
