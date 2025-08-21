<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _Infix extends Symbol
{

    public function __construct(Parser $outerInstance, ?string $id, int $bp = 0)
    {
        $lbp = $bp !== 0 ? $bp : (Tokenizer::operators[$id] ?? 0);
        parent::__construct($outerInstance, $id, $lbp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression($this->lbp);
        $this->type = "binary";
        return $this;
    }
}