<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixOrderBy extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "^", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $left): Symbol
    {
        $this->outerInstance->advance("(");
        $terms = [];
        while (true) {
            $term = new Symbol($this->outerInstance);
            $term->descending = false;

            if ($this->outerInstance->node->id === "<") {
                $this->outerInstance->advance("<");
            } elseif ($this->outerInstance->node->id === ">") {
                $term->descending = true;
                $this->outerInstance->advance(">");
            }
            $term->expression = $this->outerInstance->expression(0);
            $terms[] = $term;

            if ($this->outerInstance->node->id !== ",") {
                break;
            }
            $this->outerInstance->advance(",");
        }
        $this->outerInstance->advance(")");
        $this->lhs = $left;
        $this->rhsTerms = $terms;
        $this->type = "binary";
        return $this;
    }
}