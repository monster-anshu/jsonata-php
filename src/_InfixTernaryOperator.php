<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixTernaryOperator extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "?", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $symbol): Symbol
    {
        $this->type = "condition";
        $this->condition = $symbol;
        $this->then = $this->outerInstance->expression(0);
        if ($this->outerInstance->node->id === ":") {
            $this->outerInstance->advance(":");
            $this->_else = $this->outerInstance->expression(0);
        }

        return $this;
    }
}
