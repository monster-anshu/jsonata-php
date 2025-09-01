<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixCoalesce extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {

        parent::__construct($parser, "??", $bp);
        $this->construct_args = func_get_args();
    }

    /**
     * @param \Monster\JsonataPhp\Symbol $left
     */
    public function led($left): Symbol
    {
        $this->type = "condition";
        $cond = new Symbol($this->outerInstance);
        $cond->type = "function";
        $cond->value = "(";

        $proc = new Symbol($this->outerInstance);
        $proc->type = "variable";
        $proc->value = "exists";

        $cond->procedure = $proc;
        $cond->arguments = [$left];

        $this->condition = $cond;
        $this->then = $left;
        $this->_else = $this->outerInstance->expression(0);
        return $this;
    }
}
