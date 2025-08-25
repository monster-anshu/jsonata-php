<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixFunctionInvocation extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "(", $bp);
        $this->construct_args = func_get_args();
    }

    public function led(Symbol $left): Symbol
    {
        $this->procedure = $left;
        $this->type = "function";
        $this->arguments = [];

        if ($this->outerInstance->node->id !== ")") {
            while (true) {
                if (
                    $this->outerInstance->node->type === "operator" &&
                    $this->outerInstance->node->id === "?"
                ) {
                    $this->type = "partial";
                    $this->arguments[] = $this->outerInstance->node;
                    $this->outerInstance->advance("?");
                } else {
                    $this->arguments[] = $this->outerInstance->expression(0);
                }
                if ($this->outerInstance->node->id !== ",") {
                    break;
                }
                $this->outerInstance->advance(",");
            }
        }

        $this->outerInstance->advance(")", true);

        // lambda function
        if ($left->type === "name" && ($left->value === "function" || $left->value === "λ")) {
            foreach ($this->arguments as $arg) {
                if ($arg->type !== "variable") {
                    return $this->outerInstance->handleError(
                        new JException("S0208", $arg->position, $arg->value)
                    );
                }
            }
            $this->type = "lambda";

            if ($this->outerInstance->node->id === "<") {
                $depth = 1;
                $sig = "<";
                while (
                    $depth > 0 && $this->outerInstance->node->id !== "{" &&
                    $this->outerInstance->node->id !== "(end)"
                ) {
                    $tok = $this->outerInstance->advance();
                    if ($tok->id === ">") {
                        $depth -= 1;
                    } elseif ($tok->id === "<") {
                        $depth += 1;
                    }
                    $sig .= $tok->value;
                }
                $this->outerInstance->advance(">");
                $this->signature = new Signature($sig, "lambda");
            }

            $this->outerInstance->advance("{");
            $this->body = $this->outerInstance->expression(0);
            $this->outerInstance->advance("}");
        }

        return $this;
    }

    public function nud(): Symbol
    {
        $expressions = [];
        while ($this->outerInstance->node->id !== ")") {
            $expressions[] = $this->outerInstance->expression(0);
            if ($this->outerInstance->node->id !== ";") {
                break;
            }
            $this->outerInstance->advance(";");
        }
        $this->outerInstance->advance(")", true);
        $this->type = "block";
        $this->expressions = $expressions;
        return $this;
    }
}