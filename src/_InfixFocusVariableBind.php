<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixFocusVariableBind extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "@", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $left): Symbol
    {
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators["@"]);
        if ($this->rhs->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0214", $this->rhs->position, "@"));
        }
        $this->type = "binary";
        return $this;
    }
}
