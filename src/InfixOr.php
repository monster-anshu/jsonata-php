<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class InfixOr extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "or");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}