<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;


class InfixRBindVariable extends InfixR
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, ":=", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        if ($left->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0212", $left->position, $left->value));
        }
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators[":="] - 1);
        $this->type = "binary";
        return $this;
    }
}
