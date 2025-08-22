<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixTernaryOperator extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "?", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->type = "condition";
        $this->condition = $left;
        $this->then = $this->outerInstance->expression(0);
        if ($this->outerInstance->node->id === ":") {
            $this->outerInstance->advance(":");
            $this->_else = $this->outerInstance->expression(0);
        }
        return $this;
    }
}