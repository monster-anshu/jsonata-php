<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixFocusVariableBind extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "@", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $symbol): Symbol
    {
        $this->lhs = $symbol;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators["@"]);
        if ($this->rhs->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0214", $this->rhs->position, "@"));
        }

        $this->type = "binary";
        return $this;
    }
}
