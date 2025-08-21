<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class InfixIn extends Infix
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