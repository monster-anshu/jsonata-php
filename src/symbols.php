<?php

declare(strict_types=1);

// ------------------------
// Terminal
class Terminal extends Symbol
{


    public function nud(): Symbol
    {
        return $this;
    }
}

// ------------------------
// Infix (left-associative)
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

// ------------------------
// InfixAndPrefix
class InfixAndPrefix extends Infix
{
    public Prefix $prefix;

    public function __construct(Parser $outerInstance, string $id, int $bp = 0)
    {
        parent::__construct($outerInstance, $id, $bp);
        $this->prefix = new Prefix($this->outerInstance, $id);
    }

    public function nud(): Symbol
    {
        return $this->prefix->nud();
    }

    public function __clone()
    {
        // Make sure to allocate a new Prefix when cloning
        $this->prefix = new Prefix($this->outerInstance, $this->id);
    }
}

// ------------------------
// InfixR (right-associative)
class InfixR extends Symbol
{

    // led() will be implemented in parser context if needed
}

// ------------------------
// Prefix
class Prefix extends Symbol
{

    public function nud(): Symbol
    {
        $this->expression = $this->outerInstance->expression(70);
        $this->type = "unary";
        return $this;
    }
}



class InfixFieldWildcard extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "*");
    }

    public function nud(): Symbol
    {
        $this->type = "wildcard";
        return $this;
    }
}

class InfixParentOperator extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "%");
    }

    public function nud(): Symbol
    {
        $this->type = "parent";
        return $this;
    }
}

class InfixAnd extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "and");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}

class InfixOr extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "or");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}

class InfixIn extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "in");
    }

    public function nud(): Symbol
    {
        return $this;
    }
}

class InfixRError extends Infix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "(error)", 10);
    }

    public function led(Symbol $left): Symbol
    {
        throw new \Exception("TODO");
    }
}

class PrefixDescendantWildcard extends Prefix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "**");
    }

    public function nud(): Symbol
    {
        $this->type = "descendant";
        return $this;
    }
}

class InfixFunctionInvocation extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "(", $bp);
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

class InfixArrayConstructor extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "[", $bp);
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

class InfixOrderBy extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "^", $bp);
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

class InfixObjectConstructor extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "{", $bp);
    }

    public function nud(): Symbol
    {
        return $this->outerInstance->objectParser(null);
    }

    public function led(Symbol $left): Symbol
    {
        return $this->outerInstance->objectParser($left);
    }
}

class InfixRBindVariable extends InfixR
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, ":=", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        if ($left->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0212", $left->position, $left->value));
        }
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators[":="] - 1);
        $this->type = "binary";
        return $this;
    }
}

class InfixFocusVariableBind extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "@", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators["@"]);
        if ($this->rhs->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0214", $this->rhs->position, "@"));
        }
        $this->type = "binary";
        return $this;
    }
}

class InfixIndexVariableBind extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "#", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->lhs = $left;
        $this->rhs = $this->outerInstance->expression(Tokenizer::operators["#"]);
        if ($this->rhs->type !== "variable") {
            return $this->outerInstance->handleError(new JException("S0214", $this->rhs->position, "#"));
        }
        $this->type = "binary";
        return $this;
    }
}

class InfixTernaryOperator extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "?", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->type = "condition";
        $this->condition = $left;
        $this->then = $this->outerInstance->expression(0);
        if ($this->outerInstance->node->id === ":") {
            $this->outerInstance->advance(":");
            $this->else = $this->outerInstance->expression(0);
        }
        return $this;
    }
}

class InfixCoalesce extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "??", $bp);
    }

    public function led(Symbol $left): Symbol
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
        $this->else = $this->outerInstance->expression(0);
        return $this;
    }
}

class InfixDefault extends Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "?:", $bp);
    }

    public function led(Symbol $left): Symbol
    {
        $this->type = "condition";
        $this->condition = $left;
        $this->then = $left;
        $this->else = $this->outerInstance->expression(0);
        return $this;
    }
}

class PrefixObjectTransformer extends Prefix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "|");
    }

    public function nud(): Symbol
    {
        $this->type = "transform";
        $this->pattern = $this->outerInstance->expression(0);
        $this->outerInstance->advance("|");
        $this->update = $this->outerInstance->expression(0);
        if ($this->outerInstance->node->id === ",") {
            $this->outerInstance->advance(",");
            $this->delete = $this->outerInstance->expression(0);
        }
        $this->outerInstance->advance("|");
        return $this;
    }
}