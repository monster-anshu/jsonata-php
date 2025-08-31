<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Infix extends Symbol
{
    public function __construct(Parser $parser, ?string $id, int $bp = 0)
    {
        $bp = $bp !== 0 ? $bp : (Tokenizer::operators[$id] ?? 0);
        if ($parser->dbg) {
            print_r([
                'id' => $id,
                'bp' => $bp,
            ]);
        }

        parent::__construct($parser, $id, $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $symbol): Symbol
    {
        $this->lhs = $symbol;
        $this->rhs = $this->outerInstance->expression($this->lbp);
        $this->type = "binary";
        return $this;
    }
}
