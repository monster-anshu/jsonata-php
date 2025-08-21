<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Infix extends Symbol
{

    public function __construct(Parser $outerInstance, ?string $id, int $bp = 0)
    {
        $lbp = $bp !== 0 ? $bp : (isset(Tokenizer::operators[$id]) ? Tokenizer::operators[$id] : 0);
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