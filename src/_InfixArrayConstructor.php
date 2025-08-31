<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixArrayConstructor extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "[", $bp);
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        $a = [];
        if ($this->outerInstance->node->id !== "]") {
            while (true) {
                $item = $this->outerInstance->expression(0);
                if ($this->outerInstance->node->id === "..") {
                    $range = new Symbol($this->outerInstance);
                    $range->type = "binary";
                    $range->value = "..";
                    $range->position = $this->outerInstance->node->position;
                    $range->lhs = $item;
                    $this->outerInstance->advance("..");
                    $range->rhs = $this->outerInstance->expression(0);
                    $item = $range;
                }

                $a[] = $item;
                if ($this->outerInstance->node->id !== ",") {
                    break;
                }

                $this->outerInstance->advance(",");
            }
        }

        $this->outerInstance->advance("]", true);
        $this->expressions = $a;
        $this->type = "unary";
        return $this;
    }

    public function led(Symbol $symbol): Symbol
    {
        if ($this->outerInstance->node->id === "]") {
            $step = $symbol;
            while ($step instanceof \Monster\JsonataPhp\Symbol && $step->type === "binary" && $step->value === "[") {
                $step = $step->lhs;
            }

            $step->keepArray = true;
            $this->outerInstance->advance("]");
            return $symbol;
        } else {
            $this->lhs = $symbol;
            $this->rhs = $this->outerInstance->expression(Tokenizer::operators["]"]);
            $this->type = "binary";
            $this->outerInstance->advance("]", true);
            return $this;
        }
    }
}
