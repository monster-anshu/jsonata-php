<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Parser
{

    public $dbg = false;
    public $source;
    public $recover;
    public ?Symbol $node = null;
    public $lexer;
    public $symbolTable = [];
    public $errors = [];

    /** @var Symbol[] */
    public $ancestry = [];
    public int $ancestorLabel = 0;
    public int $ancestorIndex = 0;

    public function __construct()
    {
        $this->register(new _Terminal($this, '(end)',0));
        $this->register(new _Terminal($this, '(name)'));
        $this->register(new _Terminal($this, '(literal)'));
        $this->register(new _Terminal($this, '(regex)'));
        $this->register(new Symbol( $this, ':'));
        $this->register(new Symbol( $this, ';'));
        $this->register(new Symbol( $this, ','));
        $this->register(new Symbol( $this, ')'));
        $this->register(new Symbol( $this, ']'));
        $this->register(new Symbol( $this, '}'));
        $this->register(new Symbol( $this, '..'));
        $this->register(new _Infix($this,'-'));
        $this->register(new _Infix($this,'*', 0));
        $this->register(new _Infix($this,'/'));
        $this->register(new _Infix($this,'%'));
        $this->register(new _Infix($this,'='));
        $this->register(new _Infix($this,'<'));
        $this->register(new _Infix($this,'>'));
        $this->register(new _Infix($this,'!='));
        $this->register(new _Infix($this,'<='));
        $this->register(new _Infix($this,'>='));
        $this->register(new _Infix($this,'&'));
        $this->register(new _Infix($this,'and'));
        $this->register(new _Infix($this,'or'));
        $this->register(new _Infix($this,'in'));
        $this->register(new _Infix($this,'~>'));
        $this->register(new _Infix($this,'??', Tokenizer::operators['??']));
        $this->register(new _InfixR($this,'(error)', 10));
        $this->register(new _Prefix($this,'**'));
        $this->register(new _Infix($this,'(', Tokenizer::operators['(']));
        $this->register(new _Infix($this,'[', Tokenizer::operators['[']));
        $this->register(new _Infix($this,'^', Tokenizer::operators['^']));
        $this->register(new _Infix($this,'{', Tokenizer::operators['{']));
        $this->register(new _InfixR($this,':=', Tokenizer::operators[':=']));
        $this->register(new _Infix($this,'@', Tokenizer::operators['@']));
        $this->register(new _Infix($this,'#', Tokenizer::operators['#']));
        $this->register(new _Infix($this,'?', Tokenizer::operators['?']));
        $this->register(new _Infix($this,'?:', Tokenizer::operators['?:']));
        $this->register(new _Prefix($this,'|'));
    }



    public function tailCallOptimize(?Symbol $expr): Symbol
    {
        $result = null;

        if ($expr->type === "function" && $expr->predicate === null) {
            // Replace function with a thunk for tail-call optimization
            $thunk = new Symbol($this,"");
            $thunk->type = "lambda";
            $thunk->thunk = true;
            $thunk->arguments = [];   // empty arguments list
            $thunk->position = $expr->position;
            $thunk->body = $expr;

            $result = $thunk;

        } elseif ($expr->type === "condition") {
            // Analyze both branches
            $expr->then = $this->tailCallOptimize($expr->then);
            if (isset($expr->else) && $expr->else !== null) {
                $expr->else = $this->tailCallOptimize($expr->else);
            }
            $result = $expr;

        } elseif ($expr->type === "block") {
            // Only optimize the last expression in the block
            $length = count($expr->expressions);
            if ($length > 0) {
                $expr->expressions[$length - 1] = $this->tailCallOptimize($expr->expressions[$length - 1]);
            }
            $result = $expr;

        } else {
            // All other expressions are returned as-is
            $result = $expr;
        }

        return $result;
    }

    public function parse($jsonata)
    {
        $this->source = $jsonata;
        $this->lexer = new Tokenizer($this->source);
        $this->advance();
        $expr = $this->expression(0);
        if ($this->node->id !== '(end)') {
            $err = new JException("S0201", $this->node->position, $this->node->value);
            $this->handleError($err);
        }
        $expr = $this->processAST($expr);
        if ($expr->type === 'parent' || ($expr->seekingParent ?? null) !== null) {
            throw new JException("S0217", $expr->position, $expr->type);
        }
        if (count($this->errors) > 0) {
            $expr->errors = $this->errors;
        }
        return $expr;
    }

    public function advance($id = null, $infix = false)
    {
        if ($id !== null && $this->node->id !== $id) {
            $code = $this->node->id === '(end)' ? "S0203" : "S0202";
            $err = new JException($code, $this->node->position, $this->node->value);
            return $this->handleError($err);
        }
        $next_token = $this->lexer->next($infix);
        if ($next_token === null) {
            $this->node = $this->symbolTable['(end)'];
            $this->node->position = strlen($this->source);
            return $this->node;
        }
        $value = $next_token->value;
        $type = $next_token->type;
        $symbol = null;
        switch ($type) {
            case 'name':
            case 'variable':
                $symbol = $this->symbolTable['(name)'];
                break;
            case 'operator':
                $symbol = $this->symbolTable[$value] ?? null;
                if ($symbol === null) {
                    return $this->handleError(new JException("S0204", $next_token->position, $value));
                }
                break;
            case 'string':
            case 'number':
            case 'value':
                $symbol = $this->symbolTable['(literal)'];
                break;
            case 'regex':
                $type = 'regex';
                $symbol = $this->symbolTable['(regex)'];
                break;
            default:
                return $this->handleError(new JException("S0205", $next_token->position, $value));
        }
        $this->node = $symbol->create();
        $this->node->value = $value;
        $this->node->type = $type;
        $this->node->position = $next_token->position;
        return $this->node;
    }

    public function expression($rbp)
    {
        $left = null;
        $t = $this->node;
        $this->advance(null, true);
        $left = $t->nud();
        while ($rbp < $this->node->lbp) {
            $t = $this->node;
            $this->advance(null, false);
            $left = $t->led($left);
        }
        return $left;
    }

    public function handleError(JException $err)
    {
        if ($this->recover) {
            $err->remaining = $this->remainingTokens();
            $this->errors[] = $err;
            $node = new Symbol($this, 'null');
            return $node;
        } else {
            throw $err;
        }
    }

    public function remainingTokens()
    {
        $remaining = [];
        if ($this->node->id !== '(end)') {
            $t = new JsonataToken(
                $this->node->type,
                $this->node->value,
                $this->node->position
            );
            $remaining[] = $t;
        }
        $nxt = $this->lexer->next(false);
        while ($nxt !== null) {
            $remaining[] = $nxt;
            $nxt = $this->lexer->next(false);
        }
        return $remaining;
    }

    public function register(Symbol $t)
    {
        $s = $this->symbolTable[$t->id] ?? null;
        if ($s !== null) {
            if ($t->bp >= $s->lbp) {
                $s->lbp = $t->bp;
            }
        } else {
            $s = $t->create();
            $s->value = $s->id = $t->id;
            $s->lbp = $t->bp;
            $this->symbolTable[$t->id] = $s;
        }
    }

    /**
     * Parses an object literal.
     * This can be a prefix operator (e.g., {"a": 1}) or an infix operator.
     *
     * @param Symbol|null $left The symbol on the left-hand side, if this is an infix operation.
     * @return Symbol The resulting Symbol node for the object.
     */
    public function objectParser(?Symbol $left): Symbol
    {
        // If $left is not null, it's an infix operation; otherwise, it's a prefix one.
        $res = $left !== null ? new _Infix($this,"{") : new _Prefix($this,"{");

        // This array will hold the key/value pairs of the object.
        $a = [];

        // Check if the object is not empty (i.e., not just "{}").
        if ($this->node->id !== "}") {
            // Loop indefinitely until we find the closing brace.
            while (true) {
                $n = $this->expression(0); // Parse the key (name).
                $this->advance(":");       // Expect and consume the colon.
                $v = $this->expression(0); // Parse the value.

                // Add the [key, value] pair to our array.
                $a[] = [$n, $v];

                // If the next token is not a comma, we're done with the pairs.
                if ($this->node->id !== ",") {
                    break;
                }
                // Otherwise, consume the comma and parse the next pair.
                $this->advance(",");
            }
        }

        // Expect and consume the closing brace.
        $this->advance("}", true);

        if ($left === null) {
            // It's a prefix expression (e.g., a standalone object literal).
            // In PHP, type casting like in Java isn't needed.
            $res->lhsObject = $a;
            $res->type = "unary";
        } else {
            // It's an infix expression.
            $res->lhs = $left;
            $res->rhsObject = $a;
            $res->type = "binary";
        }

        return $res;
    }

    /**
     * Recursively seeks the parent of a node in the abstract syntax tree.
     *
     * @param Symbol $node The current node to inspect.
     * @param Symbol $slot The symbol tracking the parent search state.
     * @return Symbol The modified slot after inspection.
     * @throws JException If an unexpected node type is encountered.
     */
    public function seekParent(Symbol $node, Symbol $slot): Symbol
    {
        switch ($node->type) {
            case "name":
            case "wildcard":
                $slot->level--;
                if ($slot->level == 0) {
                    if ($node->ancestor === null) {
                        $node->ancestor = $slot;
                    } else {
                        // Reuse the existing label from the ancestry list
                        $this->ancestry[(int) $slot->index]->slot->label = $node->ancestor->label;
                        $node->ancestor = $slot;
                    }
                    $node->tuple = true;
                }
                break;

            case "parent":
                $slot->level++;
                break;

            case "block":
                // Look in the last expression in the block
                if (count($node->expressions) > 0) {
                    $node->tuple = true;
                    $lastExpression = $node->expressions[count($node->expressions) - 1];
                    $slot = $this->seekParent($lastExpression, $slot);
                }
                break;

            case "path":
                // Start with the last step in the path
                $node->tuple = true;
                $index = count($node->steps) - 1;

                // Recurse backwards through the path steps
                if ($index >= 0) {
                    $slot = $this->seekParent($node->steps[$index--], $slot);
                    while ($slot->level > 0 && $index >= 0) {
                        // Check previous steps if needed
                        $slot = $this->seekParent($node->steps[$index--], $slot);
                    }
                }
                break;

            default:
                // Error - can't derive ancestor from this node type
                throw new JException("S0217", $node->position, $node->type);
        }

        return $slot;
    }

    /**
     * Pushes ancestry information from one symbol to another.
     *
     * This function checks if a `$value` symbol is seeking a parent and merges
     * that information into the `$result` symbol's ancestry list.
     *
     * @param Symbol      $result The symbol to which ancestry information will be added.
     * @param Symbol|null $value  The symbol containing the source ancestry information.
     * @return void This method modifies the $result object directly.
     */

    private function pushAncestry(Symbol $result, ?Symbol $value): void
    {
        // Corresponds to the NPE (Null Pointer Exception) check in Java.
        if ($value === null) {
            return;
        }

        // Check if the value is seeking a parent or is a 'parent' type itself.
        if ($value->seekingParent !== null || $value->type === 'parent') {
            // If 'seekingParent' exists, use it; otherwise, start with an empty array.
            $slots = $value->seekingParent ?? [];

            // If the value is a 'parent' type, add its own slot to the list.
            if ($value->type === 'parent') {
                $slots[] = $value->slot;
            }

            // Merge the collected slots into the result's 'seekingParent' list.
            if ($result->seekingParent === null) {
                $result->seekingParent = $slots;
            } else {
                // array_merge combines the two arrays.
                $result->seekingParent = array_merge($result->seekingParent, $slots);
            }
        }
    }


    /**
     * Resolves the ancestry for a 'path' symbol by iterating backwards
     * through its steps to find the appropriate parent scope.
     *
     * @param Symbol $path The path symbol to resolve.
     * @return void This method modifies the $path object in place.
     */
    private function resolveAncestry(Symbol $path): void
    {
        // If there are no steps in the path, there's nothing to resolve.
        if (empty($path->steps)) {
            return;
        }

        // Get the last step in the path to start the process.
        // `end()` is a convenient PHP function to get the last element of an array.
        $laststep = end($path->steps);

        // Use the null coalescing operator (??) as a shorthand.
        $slots = $laststep->seekingParent ?? [];

        if ($laststep->type === 'parent') {
            $slots[] = $laststep->slot;
        }

        // A `foreach` loop is more idiomatic in PHP for iterating over arrays.
        foreach ($slots as $slot) {
            $index = count($path->steps) - 2; // Start with the second-to-last step.

            while ($slot->level > 0) {
                if ($index < 0) {
                    // If we've run out of steps, the ancestry search is promoted
                    // to the path's parent.
                    if ($path->seekingParent === null) {
                        $path->seekingParent = [];
                    }
                    $path->seekingParent[] = $slot;
                    break;
                }

                // Get the previous step.
                $step = $path->steps[$index--];

                // Skip multiple contiguous steps that bind the focus.
                while ($index >= 0 && $step->focus !== null && $path->steps[$index]->focus !== null) {
                    $step = $path->steps[$index--];
                }

                // Delegate to seekParent to process the step.
                $slot = $this->seekParent($step, $slot);
            }
        }
    }



    /**
     * Post-parse stage to add semantic value to the parse tree.
     * This flattens location paths and simplifies the AST to make evaluation easier.
     *
     * @param Symbol|null $expr The expression node to process.
     * @return Symbol|null The processed (transformed) expression node.
     * @throws JException
     */
    private function processAST(?Symbol $expr): ?Symbol
    {
        $result = $expr;
        if ($expr === null) {
            return null;
        }

        if ($this->dbg) {
            echo " > processAST type=" . ($expr->type ?? 'null') . " value='" . ($expr->value ?? '') . "'\n";
        }

        switch ($expr->type ?? '(null)') {
            case 'binary':
                switch ((string) $expr->value) {
                    case '.':
                        $lstep = $this->processAST($expr->lhs);

                        if ($lstep->type === 'path') {
                            $result = $lstep;
                        } else {
                            $result = new _Infix($this,null);
                            $result->type = 'path';
                            $result->steps = [$lstep];
                        }

                        if ($lstep->type === 'parent') {
                            $result->seekingParent = [$lstep->slot];
                        }

                        $rest = $this->processAST($expr->rhs);
                        $lastResultStep = end($result->steps);

                        if ($rest->type === 'function' && ($rest->procedure->type ?? null) === 'path' && count($rest->procedure->steps) === 1 && $rest->procedure->steps[0]->type === 'name' && $lastResultStep->type === 'function') {
                            $lastResultStep->next_function ;
                             $rest->procedure->steps[0]->value;
                        }

                        if (($rest->type ?? null) === 'path') {
                            $result->steps = array_merge($result->steps, $rest->steps);
                        } else {
                            if (isset($rest->predicate)) {
                                $rest->stages = $rest->predicate;
                                unset($rest->predicate);
                            }
                            $result->steps[] = $rest;
                        }

                        foreach ($result->steps as $step) {
                            if ($step->type === 'number' || $step->type === 'value') {
                                throw new JException("S0213", $step->position, $step->value);
                            }
                            if ($step->type === 'string') {
                                $step->type = 'name';
                            }
                        }

                        if (count(array_filter($result->steps, fn($step) => $step->keepArray ?? false)) > 0) {
                            $result->keepSingletonArray = true;
                        }

                        if (!empty($result->steps)) {
                            $firststep = $result->steps[0];
                            if ($firststep->type === 'unary' && (string) $firststep->value === '[') {
                                $firststep->consarray = true;
                            }
                            $laststep = end($result->steps);
                            if ($laststep->type === 'unary' && (string) $laststep->value === '[') {
                                $laststep->consarray = true;
                            }
                        }

                        $this->resolveAncestry($result);
                        break;

                    case '[':
                        $result = $this->processAST($expr->lhs);
                        $step = $result;
                        $type = 'predicate';

                        if ($result->type === 'path') {
                            $step = end($result->steps);
                            $type = 'stages';
                        }

                        if (isset($step->group)) {
                            throw new JException("S0209", $expr->position);
                        }

                        if ($type === 'stages') {
                            $step->stages = $step->stages ?? [];
                        } else {
                            $step->predicate = $step->predicate ?? [];
                        }

                        $predicate = $this->processAST($expr->rhs);
                        if ($predicate->seekingParent !== null) {
                            foreach ($predicate->seekingParent as $slot) {
                                if ($slot->level === 1) {
                                    $this->seekParent($step, $slot);
                                } else {
                                    $slot->level--;
                                }
                            }
                            $this->pushAncestry($step, $predicate);
                        }

                        $s = new Symbol($this);
                        $s->type = 'filter';
                        $s->expr = $predicate;
                        $s->position = $expr->position;

                        if ($expr->keepArray ?? false) {
                            $step->keepArray = true;
                        }

                        if ($type === 'stages') {
                            $step->stages[] = $s;
                        } else {
                            $step->predicate[] = $s;
                        }
                        break;

                    case '{': // group-by
                        $result = $this->processAST($expr->lhs);
                        if (isset($result->group)) {
                            throw new JException("S0210", $expr->position);
                        }
                        $result->group = new Symbol($this);
                        $result->group->lhsObject = array_map(
                            fn($pair) => [$this->processAST($pair[0]), $this->processAST($pair[1])],
                            $expr->rhsObject
                        );
                        $result->group->position = $expr->position;
                        break;

                    case '^': // order-by
                        $result = $this->processAST($expr->lhs);
                        if ($result->type !== 'path') {
                            $pathResult = new Symbol($this);
                            $pathResult->type = 'path';
                            $pathResult->steps = [$result];
                            $result = $pathResult;
                        }

                        $sortStep = new Symbol($this);
                        $sortStep->type = 'sort';
                        $sortStep->position = $expr->position;
                        $sortStep->terms = array_map(function ($terms) use ($sortStep) {
                            $expression = $this->processAST($terms->expression);
                            $this->pushAncestry($sortStep, $expression);
                            $res = new Symbol($this);
                            $res->descending = $terms->descending;
                            $res->expression = $expression;
                            return $res;
                        }, $expr->rhsTerms);

                        $result->steps[] = $sortStep;
                        $this->resolveAncestry($result);
                        break;

                    case ':=':
                        $result = new Symbol($this);
                        $result->type = 'bind';
                        $result->value = $expr->value;
                        $result->position = $expr->position;
                        $result->lhs = $this->processAST($expr->lhs);
                        $result->rhs = $this->processAST($expr->rhs);
                        $this->pushAncestry($result, $result->rhs);
                        break;

                    case '@':
                        $result = $this->processAST($expr->lhs);
                        $step = ($result->type === 'path') ? end($result->steps) : $result;

                        if (isset($step->stages) || isset($step->predicate)) {
                            throw new JException("S0215", $expr->position);
                        }
                        if ($step->type === 'sort') {
                            throw new JException("S0216", $expr->position);
                        }
                        if ($expr->keepArray ?? false) {
                            $step->keepArray = true;
                        }
                        $step->focus = $expr->rhs->value;
                        $step->tuple = true;
                        break;

                    case '#':
                        $result = $this->processAST($expr->lhs);
                        $step = $result;
                        if ($result->type !== 'path') {
                            $pathResult = new Symbol($this);
                            $pathResult->type = 'path';
                            $pathResult->steps = [$result];
                            $result = $pathResult;
                            if (isset($step->predicate)) {
                                $step->stages = $step->predicate;
                                unset($step->predicate);
                            }
                        } else {
                            $step = end($result->steps);
                        }

                        if (!isset($step->stages)) {
                            $step->index = $expr->rhs->value;
                        } else {
                            $indexSymbol = new Symbol($this);
                            $indexSymbol->type = 'index';
                            $indexSymbol->value = $expr->rhs->value;
                            $indexSymbol->position = $expr->position;
                            $step->stages[] = $indexSymbol;
                        }
                        $step->tuple = true;
                        break;

                    case '~>':
                        $result = new Symbol($this);
                        $result->type = 'apply';
                        $result->value = $expr->value;
                        $result->position = $expr->position;
                        $result->lhs = $this->processAST($expr->lhs);
                        $result->rhs = $this->processAST($expr->rhs);
                        $result->keepArray = ($result->lhs->keepArray ?? false) || ($result->rhs->keepArray ?? false);
                        break;

                    default:
                        $newResult = new _Infix($this,null);
                        $newResult->type = $expr->type;
                        $newResult->value = $expr->value;
                        $newResult->position = $expr->position;
                        $newResult->lhs = $this->processAST($expr->lhs);
                        $newResult->rhs = $this->processAST($expr->rhs);
                        $this->pushAncestry($newResult, $newResult->lhs);
                        $this->pushAncestry($newResult, $newResult->rhs);
                        $result = $newResult;
                        break;
                }
                break; // end binary

            case 'unary':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->value = $expr->value;
                $result->position = $expr->position;
                $exprValue = (string) $expr->value;

                if ($exprValue === '[') { // Array constructor
                    $result->expressions = array_map(function ($item) use ($result) {
                        $value = $this->processAST($item);
                        $this->pushAncestry($result, $value);
                        return $value;
                    }, $expr->expressions);
                } elseif ($exprValue === '{') { // Object constructor
                    $result->lhsObject = array_map(function ($pair) use ($result) {
                        $key = $this->processAST($pair[0]);
                        $this->pushAncestry($result, $key);
                        $value = $this->processAST($pair[1]);
                        $this->pushAncestry($result, $value);
                        return [$key, $value];
                    }, $expr->lhsObject);
                } else { // Other unary expressions
                    $result->expression = $this->processAST($expr->expression);
                    if ($exprValue === '-' && ($result->expression->type ?? null) === 'number') {
                        $result = $result->expression;
                        $result->value = Utils::convertNumber(-(float) $result->value);
                    } else {
                        $this->pushAncestry($result, $result->expression);
                    }
                }
                break; // end unary

            case 'function':
            case 'partial':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->name = $expr->name;
                $result->value = $expr->value;
                $result->position = $expr->position;
                $result->arguments = array_map(function ($arg) use ($result) {
                    $argAST = $this->processAST($arg);
                    $this->pushAncestry($result, $argAST);
                    return $argAST;
                }, $expr->arguments);
                $result->procedure = $this->processAST($expr->procedure);
                break;

            case 'lambda':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->arguments = $expr->arguments;
                $result->signature = $expr->signature;
                $result->position = $expr->position;
                $body = $this->processAST($expr->body);
                $result->body = $this->tailCallOptimize($body);
                break;

            case 'condition':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->position = $expr->position;
                $result->condition = $this->processAST($expr->condition);
                $this->pushAncestry($result, $result->condition);
                $result->then = $this->processAST($expr->then);
                $this->pushAncestry($result, $result->then);
                if (isset($expr->else)) {
                    $result->else = $this->processAST($expr->else);
                    $this->pushAncestry($result, $result->else);
                }
                break;

            case 'transform':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->position = $expr->position;
                $result->pattern = $this->processAST($expr->pattern);
                $result->update = $this->processAST($expr->update);
                if (isset($expr->delete)) {
                    $result->delete = $this->processAST($expr->delete);
                }
                break;

            case 'block':
                $result = new Symbol($this);
                $result->type = $expr->type;
                $result->position = $expr->position;
                $result->expressions = array_map(function ($item) use ($result) {
                    $part = $this->processAST($item);
                    $this->pushAncestry($result, $part);
                    if (($part->consarray ?? false) || ($part->type === 'path' && ($part->steps[0]->consarray ?? false))) {
                        $result->consarray = true;
                    }
                    return $part;
                }, $expr->expressions);
                break;

            case 'name':
                $result = new Symbol($this);
                $result->type = 'path';
                $result->steps = [$expr];
                if ($expr->keepArray ?? false) {
                    $result->keepSingletonArray = true;
                }
                break;

            case 'parent':
                $result = new Symbol($this);
                $result->type = 'parent';
                $result->slot = new Symbol($this);
                $result->slot->label = '!' . $this->ancestorLabel++;
                $result->slot->level = 1;
                $result->slot->index = $this->ancestorIndex++;
                $this->ancestry[] = $result;
                break;

            case 'operator':
                if (in_array($expr->value, ['and', 'or', 'in'])) {
                    $expr->type = 'name';
                    $result = $this->processAST($expr);
                } elseif ((string) $expr->value === '?') {
                    $result = $expr;
                } else {
                    throw new JException("S0201", $expr->position, $expr->value);
                }
                break;

            case 'string':
            case 'number':
            case 'value':
            case 'wildcard':
            case 'descendant':
            case 'variable':
            case 'regex':
                // These types need no further processing
                $result = $expr;
                break;

            case 'error':
                $result = $expr;
                if (isset($expr->lhs)) {
                    $result = $this->processAST($expr->lhs);
                }
                break;

            default:
                $code = ($expr->id === '(end)') ? "S0207" : "S0206";
                $err = new JException($code, $expr->position, $expr->value);
                if ($this->recover) {
                    $this->errors[] = $err;
                    $ret = new Symbol($this);
                    $ret->type = 'error';
                    $ret->error = $err;
                    return $ret;
                } else {
                    throw $err;
                }
        }

        if ($expr->keepArray ?? false) {
            $result->keepArray = true;
        }

        return $result;
    }
}

?>