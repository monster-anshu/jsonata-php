<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixAnd extends _Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "and");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}