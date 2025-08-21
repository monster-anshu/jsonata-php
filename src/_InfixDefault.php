<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixDefault extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "?:", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->type = "condition";
        $this->condition = $left;
        $this->then = $left;
        $this->else = $this->outerInstance->expression(0);
        return $this;
    }
}