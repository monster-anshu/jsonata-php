<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixIn extends _Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "in");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}