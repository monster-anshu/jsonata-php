<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixRBindVariable extends _InfixR
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, ":=", $bp);
        $this->construct_args = func_get_args();
    }

    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     */
    public function led($symbol): Symbol
    {
        if ($symbol->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0212", $symbol->position, $symbol->value));
        }

        $this->lhs = $symbol;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators[":="] - 1);
        $this->type = "binary";
        return $this;
    }
}
