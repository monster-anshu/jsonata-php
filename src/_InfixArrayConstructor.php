<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixArrayConstructor extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "[", $bp);
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

    public function led(Symbol $left): Symbol
    {
        if ($this->outerInstance->node->id === "]") {
            $step = $left;
            while ($step !== null && $step->type === "binary" && $step->value === "[") {
                $step = $step->lhs;
            }
            $step->keepArray = true;
            $this->outerInstance->advance("]");
            return $left;
        } else {
            $this->lhs = $left;
            $this->rhs = $this->outerInstance->expression(Tokenizer::operators["]"]);
            $this->type = "binary";
            $this->outerInstance->advance("]", true);
            return $this;
        }
    }
}
