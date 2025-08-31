<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Infix extends Symbol
{
    public function __construct(Parser $outerInstance, ?string $id, int $bp = 0)
    {
        $bp = $bp !== 0 ? $bp : (Tokenizer::operators[$id] ?? 0);
        if ($outerInstance->dbg) {
            print_r([
                'id' => $id,
                'bp' => $bp,
            ]);
        }
        parent::__construct($outerInstance, $id, $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $left): Symbol
    {
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression($this->lbp);
        $this->type = "binary";
        return $this;
    }
}
