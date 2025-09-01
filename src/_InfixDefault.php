<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixDefault extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "?:", $bp);
        $this->construct_args = func_get_args();
    }

    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     */
    public function led($symbol): Symbol
    {
        $this->type = "condition";
        $this->condition = $symbol;
        $this->then = $symbol;
        $this->_else = $this->outerInstance->expression(0);
        return $this;
    }
}
