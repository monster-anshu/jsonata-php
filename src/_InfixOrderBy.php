<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixOrderBy extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "^", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $symbol): Symbol
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
        $this->lhs = $symbol;
        $this->rhsTerms = $terms;
        $this->type = "binary";
        return $this;
    }
}
