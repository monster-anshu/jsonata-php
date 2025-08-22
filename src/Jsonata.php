<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

use Exception;

class Jsonata
{
    private readonly Parser $parser;
    private mixed $input;
    private ?_Frame $environment = null;
    private static ?_Frame $staticFrame = null; // equivalent to: static Frame staticFrame;
    public function createFrame(?_Frame $enclosingEnvironment = null): _Frame
    {
        if ($enclosingEnvironment) {
            return new _Frame($enclosingEnvironment);

        }
        return new _Frame(null);
    }


    private static ?Symbol $chainAST = null;

    public static function chainAST(): Symbol
    {
        if (self::$chainAST === null) {
            self::$chainAST = (new Parser())->parse(
                "function(\$f, \$g) { function(\$x){ \$g(\$f(\$x)) } }"
            );
        }
        return self::$chainAST;
    }

    private function createFrameFromTuple(_Frame $environment, ?array $tuple): _Frame
    {
        $frame = $this->createFrame($environment);

        if ($tuple !== null) {
            foreach ($tuple as $prop => $value) {
                $frame->bind($prop, $value);
            }
        }

        return $frame;
    }
    private function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Evaluate expression against input data
     *
     * @param Symbol $expr JSONata expression
     * @param mixed $input Input data to evaluate against
     * @param _Frame|null $environment Environment
     * @return mixed Evaluated input data
     */
    public function evaluate(Symbol $expr, mixed $input, ?_Frame $environment = null): mixed
    {
        // Thread safety:
        // Make sure each evaluate is executed on an instance per thread
        return $this->getPerThreadInstance()->_evaluate($expr, $input, $environment);
    }

    /**
     * In Java, this would return a thread-local instance.
     * In PHP, we'll just return $this or create a new instance
     * depending on your design.
     */
    private function getPerThreadInstance(): self
    {
        // PHP doesn’t have Java-style thread-locals,
        // so usually we just reuse $this.
        return $this;
    }

    /**
     * Placeholder for the actual evaluator.
     * Equivalent to Java's private _evaluate(...)
     */
    private function _evaluate(Symbol $expr, mixed $input, _Frame $environment): mixed
    {
        $result = null;

        // Store the current input + environment
        $this->input = $input;
        $this->environment = $environment;

        if ($this->parser->dbg) {
            echo "eval expr=" . json_encode($expr) . " type=" . ($expr->type ?? "null") . PHP_EOL;
        }

        $entryCallback = $environment->lookup("__evaluate_entry");
        if ($entryCallback !== null && $entryCallback instanceof _EntryCallback) {
            $entryCallback->callback($expr, $input, $environment);
        }

        if (!empty($expr->type)) {
            switch ($expr->type) {
                case "path":
                    $result = $this->evaluatePath($expr, $input, $environment);
                    break;
                case "binary":
                    $result = $this->evaluateBinary($expr, $input, $environment);
                    break;
                case "unary":
                    $result = $this->evaluateUnary($expr, $input, $environment);
                    break;
                case "name":
                    $result = $this->evaluateName($expr, $input, $environment);
                    if ($this->parser->dbg) {
                        echo "evalName " . json_encode($result) . PHP_EOL;
                    }
                    break;
                case "string":
                case "number":
                case "value":
                    $result = $this->evaluateLiteral($expr);
                    break;
                case "wildcard":
                    $result = $this->evaluateWildcard($expr, $input);
                    break;
                case "descendant":
                    $result = $this->evaluateDescendants($expr, $input);
                    break;
                case "parent":
                    $result = $environment->lookup($expr->slot->label ?? "");
                    break;
                case "condition":
                    $result = $this->evaluateCondition($expr, $input, $environment);
                    break;
                case "block":
                    $result = $this->evaluateBlock($expr, $input, $environment);
                    break;
                case "bind":
                    $result = $this->evaluateBindExpression($expr, $input, $environment);
                    break;
                case "regex":
                    $result = $this->evaluateRegex($expr);
                    break;
                case "function":
                    $result = $this->evaluateFunction($expr, $input, $environment, Utils::$none);
                    break;
                case "variable":
                    $result = $this->evaluateVariable($expr, $input, $environment);
                    break;
                case "lambda":
                    $result = $this->evaluateLambda($expr, $input, $environment);
                    break;
                case "partial":
                    $result = $this->evaluatePartialApplication($expr, $input, $environment);
                    break;
                case "apply":
                    $result = $this->evaluateApplyExpression($expr, $input, $environment);
                    break;
                case "transform":
                    $result = $this->evaluateTransformExpression($expr, $input, $environment);
                    break;
            }
        }

        if (!empty($expr->predicate)) {
            foreach ($expr->predicate as $pred) {
                $result = $this->evaluateFilter($pred->expr, $result, $environment);
            }
        }

        if (($expr->type ?? "") !== "path" && !empty($expr->group)) {
            $result = $this->evaluateGroupExpression($expr->group, $result, $environment);
        }

        $exitCallback = $environment->lookup("__evaluate_exit");
        if ($exitCallback !== null && $exitCallback instanceof _ExitCallback) {
            $exitCallback->callback($expr, $input, $environment, $result);
        }

        // mangle result (list of 1 element -> 1 element, empty list -> null)
        if ($result !== null && Utils::isSequence($result) && !$result->tupleStream) {
            /** @var JList $result */
            if ($expr->keepArray ?? false) {
                $result->keepSingleton = true;
            }
            if ($result->isEmpty()) {
                $result = null;
            } elseif ($result->size() === 1) {
                $result = $result->keepSingleton ? $result : $result->get(0);
            }
        }

        return $result;
    }


    /**
     * Evaluate path expression against input data
     *
     * @param Symbol $expr        JSONata expression
     * @param mixed  $input       Input data to evaluate against
     * @param _Frame  $environment Environment
     * @return mixed              Evaluated input data
     */
    private function evaluatePath(Symbol $expr, mixed $input, _Frame $environment): mixed
    {
        // expr is an array of steps
        // if the first step is a variable reference ($...), including root reference ($$),
        //   then the path is absolute rather than relative
        if ($input instanceof JList && $expr->steps[0]->type !== "variable") {
            $inputSequence = $input;
        } else {
            // if input is not an array, make it so
            $inputSequence = Utils::createSequence($input);
        }

        $resultSequence = null;
        $isTupleStream = false;
        $tupleBindings = null;

        // evaluate each step in turn
        foreach ($expr->steps as $ii => $step) {
            if ($step->tuple !== null) {
                $isTupleStream = true;
            }

            // if the first step is an explicit array constructor,
            // then just evaluate that (i.e. don’t iterate over a context array)
            if ($ii === 0 && $step->consarray) {
                $resultSequence = $this->evaluate($step, $inputSequence, $environment);
            } else {
                if ($isTupleStream) {
                    $tupleBindings = $this->evaluateTupleStep($step, $inputSequence, $tupleBindings ?? [], $environment);
                } else {
                    $resultSequence = $this->evaluateStep(
                        $step,
                        $inputSequence,
                        $environment,
                        $ii === count($expr->steps) - 1
                    );
                }
            }

            if (!$isTupleStream && ($resultSequence === null || count($resultSequence) === 0)) {
                break;
            }

            if ($step->focus === null) {
                $inputSequence = $resultSequence;
            }
        }

        if ($isTupleStream) {
            if ($expr->tuple !== null) {
                // tuple stream is carrying ancestry information - keep this
                $resultSequence = $tupleBindings;
            } else {
                $resultSequence = Utils::createSequence();
                foreach ($tupleBindings as $binding) {
                    $resultSequence[] = $binding["@"] ?? null;
                }
            }
        }

        if ($expr->keepSingletonArray) {
            // If we only got an array, wrap into JList so we can set the keepSingleton flag
            if (!($resultSequence instanceof JList)) {
                $resultSequence = new JList((array) $resultSequence);
            }

            // if the array is explicitly constructed in the expression
            // and marked to promote singleton sequences to array
            if ($resultSequence instanceof JList && $resultSequence->cons && !$resultSequence->sequence) {
                $resultSequence = Utils::createSequence($resultSequence);
            }

            $resultSequence->keepSingleton = true;
        }

        if ($expr->group !== null) {
            $resultSequence = $this->evaluateGroupExpression(
                $expr->group,
                $isTupleStream ? $tupleBindings : $resultSequence,
                $environment
            );
        }

        return $resultSequence;
    }

    /**
     * Evaluate a step within a path
     *
     * @param Symbol $expr           JSONata expression
     * @param array|JList $input     Input data to evaluate against
     * @param array|null $tupleBindings The tuple stream
     * @param _Frame $environment     Environment
     * @return array|JList           Evaluated input data
     */
    public function evaluateTupleStep(Symbol $expr, array|JList $input, ?array $tupleBindings, _Frame $environment): array|JList
    {
        $result = null;

        // Handle "sort" expressions
        if ($expr->type === "sort") {
            if ($tupleBindings !== null) {
                $result = $this->evaluateSortExpression($expr, $tupleBindings, $environment);
            } else {
                $sorted = $this->evaluateSortExpression($expr, $input, $environment);

                $result = new JList();
                $result->tupleStream = true;

                foreach ($sorted as $ss => $val) {
                    $result[] = [
                        '@' => $val,
                        $expr->index => $ss,
                    ];
                }
            }

            if ($expr->stages !== null) {
                $result = $this->evaluateStages($expr->stages, $result, $environment);
            }
            return $result;
        }

        // Otherwise, build a tuple stream
        $result = new JList();
        $result->tupleStream = true;

        $stepEnv = $environment;

        // If no tuple bindings, wrap each input item in ["@" => item]
        if ($tupleBindings === null) {
            $tupleBindings = array_values(
                array_map(
                    fn($item) => ['@' => $item],
                    array_filter($input, fn($item) => $item !== null)
                )
            );
        }

        foreach ($tupleBindings as $binding) {
            $stepEnv = $this->createFrameFromTuple($environment, $binding);

            $_res = $this->evaluate($expr, $binding['@'], $stepEnv);

            if ($_res !== null) {
                $res = is_array($_res) ? $_res : [$_res];

                foreach ($res as $bb => $val) {
                    $tuple = $binding; // clone

                    if ($_res instanceof JList && $_res->tupleStream) {
                        // merge tupleStream result
                        $tuple = array_merge($tuple, $_res[$bb]);
                    } else {
                        if ($expr->focus !== null) {
                            $tuple[$expr->focus] = $val;
                            $tuple['@'] = $binding['@'];
                        } else {
                            $tuple['@'] = $val;
                        }

                        if ($expr->index !== null) {
                            $tuple[$expr->index] = $bb;
                        }
                        if ($expr->ancestor !== null) {
                            $tuple[$expr->ancestor->label] = $binding['@'];
                        }
                    }

                    $result[] = $tuple;
                }
            }
        }

        if ($expr->stages !== null) {
            $result = $this->evaluateStages($expr->stages, $result, $environment);
        }

        return $result;
    }

    /**
     * Evaluate a step within a path
     *
     * @param Symbol $expr JSONata expression
     * @param array|JList $input Input data to evaluate against
     * @param _Frame $environment Environment
     * @param bool $lastStep Flag the last step in a path
     * @return mixed Evaluated input data
     */
    public function evaluateStep(Symbol $expr, array|JList $input, _Frame $environment, bool $lastStep): mixed
    {
        $result = null;

        if ($expr->type === "sort") {
            $result = $this->evaluateSortExpression($expr, $input, $environment);

            if ($expr->stages !== null) {
                $result = $this->evaluateStages($expr->stages, $result, $environment);
            }

            return $result;
        }

        $result = Utils::createSequence();

        foreach ($input as $item) {
            $res = $this->evaluate($expr, $item, $environment);

            if ($expr->stages !== null) {
                foreach ($expr->stages as $stage) {
                    $res = $this->evaluateFilter($stage->expr, $res, $environment);
                }
            }

            if ($res !== null) {
                $result[] = $res;
            }
        }

        $resultSequence = Utils::createSequence();

        // special case when last step
        if (
            $lastStep &&
            count($result) === 1 &&
            is_array($result[0]) &&
            !Utils::isSequence($result[0])
        ) {
            $resultSequence = $result[0];
        } else {
            // flatten the sequence
            foreach ($result as $res) {
                if (!is_array($res) || ($res instanceof JList && $res->cons)) {
                    // it's not an array - just push into the result sequence
                    $resultSequence[] = $res;
                } else {
                    // res is a sequence - flatten it into the parent sequence
                    foreach ($res as $r) {
                        $resultSequence[] = $r;
                    }
                }
            }
        }

        return $resultSequence;
    }


    /**
     * Sort / order-by operator
     *
     * @param Symbol $expr AST for operator
     * @param array|JList $input Input data to evaluate against
     * @param _Frame $environment Environment
     * @return array|JList Ordered sequence
     * @throws JException
     */
    public function evaluateSortExpression(Symbol $expr, array|JList $input, _Frame $environment): array|JList
    {
        // evaluate the lhs, then sort the results in order according to rhs expression
        $lhs = $input;
        $isTupleSort = ($input instanceof JList && $input->tupleStream) ? true : false;

        // comparator closure
        $comparator = function ($a, $b) use ($expr, $environment, $isTupleSort) {
            $comp = 0;

            foreach ($expr->terms as $term) {
                if ($comp !== 0)
                    break;

                // ---- evaluate sort term in context of $a ----
                $context = $a;
                $env = $environment;
                if ($isTupleSort) {
                    $context = $a['@'] ?? null;
                    $env = $this->createFrameFromTuple($environment, $a);
                }
                $aa = $this->evaluate($term->expression, $context, $env);

                // ---- evaluate sort term in context of $b ----
                $context = $b;
                $env = $environment;
                if ($isTupleSort) {
                    $context = $b['@'] ?? null;
                    $env = $this->createFrameFromTuple($environment, $b);
                }
                $bb = $this->evaluate($term->expression, $context, $env);

                // ---- type checks ----
                if ($aa === null) {
                    $comp = ($bb === null) ? 0 : 1;
                    continue;
                }
                if ($bb === null) {
                    $comp = -1;
                    continue;
                }

                // aa/bb must be string or number
                if (
                    !(is_string($aa) || is_numeric($aa)) ||
                    !(is_string($bb) || is_numeric($bb))
                ) {
                    throw new JException("T2008", $expr->position, $aa, $bb);
                }

                // must be same type
                $sameType = false;
                if (is_numeric($aa) && is_numeric($bb)) {
                    $sameType = true;
                } elseif (gettype($aa) === gettype($bb)) {
                    $sameType = true;
                }

                if (!$sameType) {
                    throw new JException("T2007", $expr->position, $aa, $bb);
                }

                // actual comparison
                if ($aa === $bb) {
                    continue;
                } elseif ($aa < $bb) {
                    $comp = -1;
                } else {
                    $comp = 1;
                }

                // descending order
                if (!empty($term->descending) && $term->descending === true) {
                    $comp = -$comp;
                }
            }

            return $comp;
        };

        // Perform the sort using helper
        return Functions::sort($lhs, $comparator);
    }


    /**
     * Evaluate pipeline stages
     *
     * @param Symbol[] $stages
     * @param mixed $input
     * @param _Frame $environment
     * @return mixed
     * @throws JException
     */
    public function evaluateStages(array $stages, mixed $input, _Frame $environment): mixed
    {
        $result = $input;

        foreach ($stages as $stage) {
            switch ($stage->type) {
                case "filter":
                    $result = $this->evaluateFilter($stage->expr, $result, $environment);
                    break;

                case "index":
                    if (is_array($result)) {
                        foreach ($result as $ee => &$tuple) {
                            if (is_array($tuple)) {
                                $tuple[(string) $stage->value] = $ee;
                            } elseif ($tuple instanceof \ArrayAccess) {
                                $tuple[(string) $stage->value] = $ee;
                            } elseif ($tuple instanceof \stdClass) {
                                $tuple->{(string) $stage->value} = $ee;
                            }
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    /**
     * Evaluate binary expression against input data
     *
     * @param Symbol $expr  JSONata expression
     * @param mixed $input Input data
     * @param _Frame $environment Environment
     * @return mixed
     * @throws JException
     */
    public function evaluateBinary(Symbol $expr, mixed $input, _Frame $environment): mixed
    {
        /** @var _Infix $expr */
        // $expr = $expr; // cast-like comment, PHP doesn't need actual cast
        $result = null;

        $lhs = $this->evaluate($expr->lhs, $input, $environment);
        $op = (string) $expr->value;

        if ($op === "and" || $op === "or") {
            // defer evaluation of RHS to allow short-circuiting
            $evalrhs = (fn() => $this->evaluate($expr->rhs, $input, $environment));

            try {
                return $this->evaluateBooleanExpression($lhs, $evalrhs, $op);
            } catch (\Throwable $err) {
                if (!($err instanceof JException)) {
                    throw new JException("Unexpected", $expr->position);
                }
                throw $err;
            }
        }

        $rhs = $this->evaluate($expr->rhs, $input, $environment);

        try {
            $result = match ($op) {
                "+", "-", "*", "/", "%" => $this->evaluateNumericExpression($lhs, $rhs, $op),
                "=", "!=" => $this->evaluateEqualityExpression($lhs, $rhs, $op),
                "<", "<=", ">", ">=" => $this->evaluateComparisonExpression($lhs, $rhs, $op),
                "&" => $this->evaluateStringConcat($lhs, $rhs),
                ".." => $this->evaluateRangeExpression($lhs, $rhs),
                "in" => $this->evaluateIncludesExpression($lhs, $rhs),
                default => throw new JException("Unexpected operator " . $op, $expr->position),
            };
        } catch (\Throwable $err) {
            throw $err;
        }

        return $result;
    }


    /**
     * Evaluate boolean expression against input data
     *
     * @param mixed    $lhs      LHS value
     * @param callable $evalrhs  Function to evaluate RHS value
     * @param string   $op       Operator ("and" / "or")
     * @return bool
     * @throws \Exception
     */
    public function evaluateBooleanExpression(mixed $lhs, callable $evalrhs, string $op): bool
    {
        $result = false;
        $lBool = static::boolize($lhs);

        $result = match ($op) {
            // RHS is evaluated only if needed
            "and" => $lBool && static::boolize($evalrhs()),
            // RHS is evaluated only if needed
            "or" => $lBool || static::boolize($evalrhs()),
            default => throw new \InvalidArgumentException("Unsupported boolean operator: $op"),
        };

        return $result;
    }



    public static function boolize(mixed $value): bool
    {
        $booledValue = Functions::toBoolean($value);
        return $booledValue ?? false;
    }


    /**
     * Evaluate numeric expression against input data
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @param string $op - operator
     * @return mixed Result (number or null)
     * @throws JException
     */
    public function evaluateNumericExpression(mixed $lhs, mixed $rhs, string $op): mixed
    {
        if ($lhs !== null && !Utils::isNumeric($lhs)) {
            throw new JException("T2001", -1, [$op, $lhs]);
        }
        if ($rhs !== null && !Utils::isNumeric($rhs)) {
            throw new JException("T2002", -1, [$op, $rhs]);
        }

        if ($lhs === null || $rhs === null) {
            // if either side is undefined, the result is undefined
            return null;
        }

        $lhsVal = (float) $lhs;
        $rhsVal = (float) $rhs;
        $result = 0.0;

        $result = match ($op) {
            '+' => $lhsVal + $rhsVal,
            '-' => $lhsVal - $rhsVal,
            '*' => $lhsVal * $rhsVal,
            '/' => $rhsVal == 0.0 ? NAN : $lhsVal / $rhsVal,
            '%' => fmod($lhsVal, $rhsVal),
            default => throw new JException("Unexpected operator " . $op, -1),
        };

        return Utils::convertNumber($result);
    }


    /**
     * Evaluate equality expression against input data
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @param string $op - operator ("=" or "!=")
     * @return bool Result
     */
    private function evaluateEqualityExpression(mixed $lhs, mixed $rhs, string $op): bool
    {
        $ltype = $lhs !== null ? gettype($lhs) : null;
        $rtype = $rhs !== null ? gettype($rhs) : null;

        if ($ltype === null || $rtype === null) {
            // if either side is undefined, the result is false
            return false;
        }

        // JSON might come with integers, normalize to float (like Java double)
        if (is_int($lhs) || is_float($lhs)) {
            $lhs = (float) $lhs;
        }
        if (is_int($rhs) || is_float($rhs)) {
            $rhs = (float) $rhs;
        }

        return match ($op) {
            '=' => $lhs == $rhs,
            '!=' => $lhs != $rhs,
            default => throw new JException("Unexpected operator " . $op, -1),
        };
    }


    /**
     * Evaluate comparison expression against input data
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @param string $op - operator ("<", "<=", ">", ">=")
     * @return bool|null
     * @throws JException
     */
    private function evaluateComparisonExpression(mixed $lhs, mixed $rhs, string $op): ?bool
    {
        $result = null;

        // comparable types = null, string, number
        $lcomparable = $lhs === null || is_string($lhs) || is_numeric($lhs);
        $rcomparable = $rhs === null || is_string($rhs) || is_numeric($rhs);

        // if either are not comparable, throw error
        if (!$lcomparable || !$rcomparable) {
            throw new JException("T2010", 0, $op, $lhs ?? $rhs);
        }

        // if either side is undefined (null), result is undefined
        if ($lhs === null || $rhs === null) {
            return null;
        }

        // normalize numeric comparisons
        if (is_numeric($lhs) && is_numeric($rhs)) {
            $lhs = (float) $lhs;
            $rhs = (float) $rhs;
        }
        // if types mismatch (string vs number), throw
        elseif (gettype($lhs) !== gettype($rhs)) {
            throw new JException("T2009", 0, $lhs, $rhs);
        }

        // perform comparison
        $result = match ($op) {
            '<' => $lhs < $rhs,
            '<=' => $lhs <= $rhs,
            '>' => $lhs > $rhs,
            '>=' => $lhs >= $rhs,
            default => throw new JException("Unexpected operator " . $op, -1),
        };

        return $result;
    }



    /**
     * Evaluate string concatenation against input data
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @return string
     */
    private function evaluateStringConcat(mixed $lhs, mixed $rhs): string
    {
        $lstr = "";
        $rstr = "";

        if ($lhs !== null) {
            $lstr = Functions::string($lhs, null);
        }
        if ($rhs !== null) {
            $rstr = Functions::string($rhs, null);
        }

        return $lstr . $rstr;
    }



    /**
     * Evaluate range expression against input data
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @return array|null Resultant array or null
     * @throws JException
     */
    public function evaluateRangeExpression(mixed $lhs, mixed $rhs): ?array
    {
        $result = null;

        // Type check lhs
        if ($lhs !== null && !is_int($lhs)) {
            throw new JException("T2003", -1, $lhs);
        }

        // Type check rhs
        if ($rhs !== null && !is_int($rhs)) {
            throw new JException("T2004", -1, $rhs);
        }

        if ($lhs === null || $rhs === null) {
            // if either side is undefined, the result is undefined
            return $result;
        }

        if ($lhs > $rhs) {
            // if the lhs is greater than the rhs, return undefined
            return $result;
        }

        $size = $rhs - $lhs + 1;
        if ($size > 1e7) {
            throw new JException("D2014", -1, $size);
        }

        // mimic Utils.RangeList
        // TODO: check this
        return Utils::RangeList($lhs, $rhs);
    }



    /**
     * Inclusion operator - in
     *
     * @param mixed $lhs - LHS value
     * @param mixed $rhs - RHS value
     * @return bool - true if lhs is a member of rhs
     */
    private function evaluateIncludesExpression($lhs, $rhs)
    {
        $result = false;

        if ($lhs === null || $rhs === null) {
            // if either side is undefined, the result is false
            return false;
        }

        if (!is_array($rhs)) {
            $rhs = [$rhs];
        }

        foreach ($rhs as $item) {
            $tmp = $this->evaluateEqualityExpression($lhs, $item, "=");
            if ($tmp === true) {
                $result = true;
                break;
            }
        }

        return $result;
    }


    /**
     * Evaluate unary expression against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param object $environment - Environment
     * @return mixed Evaluated input data
     * @throws JException
     */
    private function evaluateUnary($expr, $input, $environment)
    {
        $result = null;

        switch ((string) $expr->value) {
            case "-":
                $result = $this->evaluate($expr->expression, $input, $environment);
                if ($result === null) {
                    $result = null;
                } elseif (Utils::isNumeric($result)) {
                    $result = Utils::convertNumber(-((float) $result));
                } else {
                    throw new JException(
                        "D1002",
                        $expr->position,
                        $expr->value,
                        $result
                    );
                }
                break;

            case "[":
                // array constructor - evaluate each item
                $result = new JList(); // []
                $idx = 0;
                foreach ($expr->expressions as $item) {
                    $environment->isParallelCall = $idx > 0;
                    $value = $this->evaluate($item, $input, $environment);
                    if ($value !== null) {
                        if ((string) $item->value === "[") {
                            $result->add($value);
                        } else {
                            $result = Functions::append($result, $value);
                        }
                    }
                    $idx++;
                }
                if ($expr->consarray) {
                    if (!($result instanceof JList)) {
                        $result = new JList($result);
                    }
                    $result->cons = true;
                }
                break;

            case "{":
                // object constructor - apply grouping
                $result = $this->evaluateGroupExpression($expr, $input, $environment);
                break;
        }

        return $result;
    }

    /**
     * Evaluate name object against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param object $environment - Environment
     * @return mixed Evaluated input data
     */
    private function evaluateName($expr, $input, $environment)
    {
        // lookup the "name" item in the input
        return Functions::lookup($input, (string) $expr->value);
    }

    /**
     * Evaluate literal against input data
     *
     * @param object $expr - JSONata expression
     * @return mixed Evaluated input data
     */
    private function evaluateLiteral($expr)
    {
        return $expr->value ?? Utils::$nullValue;
    }
    /**
     * Evaluate wildcard against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @return mixed Evaluated input data
     */
    private function evaluateWildcard($expr, $input)
    {
        $results = Utils::createSequence();

        // handle outerWrapper in JList
        if ($input instanceof JList && $input->outerWrapper && $input->size() > 0) {
            $input = $input->get(0);
        }

        if ($input !== null && is_array($input)) {
            // input is a map (associative array in PHP)
            foreach ($input as $value) {
                if (is_array($value)) {
                    $value = $this->flatten($value, null);
                    $results = Functions::append($results, $value);
                } else {
                    $results[] = $value;
                }
            }
        } elseif (is_array($input) || $input instanceof JList) {
            // input is a list
            foreach ($input as $value) {
                if (is_array($value)) {
                    $value = $this->flatten($value, null);
                    $results = Functions::append($results, $value);
                } elseif (is_array($value)) {
                    // recursive call for nested map
                    $results = array_merge($results, $this->evaluateWildcard($expr, $value));
                } else {
                    $results[] = $value;
                }
            }
        }

        return $results;
    }

    /**
     * Returns a flattened array
     *
     * @param mixed $arg - the array to be flattened
     * @param array|null $flattened - carries the flattened array; if not defined, will initialize to []
     * @return array - the flattened array
     */
    private function flatten($arg, ?array $flattened = null): array
    {
        if ($flattened === null) {
            $flattened = [];
        }

        if (is_array($arg)) {
            foreach ($arg as $item) {
                $flattened = $this->flatten($item, $flattened);
            }
        } else {
            $flattened[] = $arg;
        }

        return $flattened;
    }


    /**
     * Evaluate descendants against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @return mixed Evaluated input data
     */
    private function evaluateDescendants($expr, $input)
    {
        $result = null;
        $resultSequence = Utils::createSequence();

        if ($input !== null) {
            // traverse all descendants of this object/array
            $this->recurseDescendants($input, $resultSequence);

            if (count($resultSequence) === 1) {
                $result = $resultSequence[0];
            } else {
                $result = $resultSequence;
            }
        }

        return $result;
    }

    /**
     * Recurse through descendants
     *
     * @param mixed $input   Input data
     * @param array &$results Results (passed by reference)
     * @return void
     */
    private function recurseDescendants($input, array &$results): void
    {
        // this is the equivalent of //* in XPath
        if (!is_array($input)) {
            $results[] = $input;
        }

        if (is_array($input)) {
            // treat as list/map — foreach works for both
            foreach ($input as $member) {
                $this->recurseDescendants($member, $results);
            }
        } elseif (is_object($input)) {
            // if it's an object, recurse through its public properties
            foreach (get_object_vars($input) as $member) {
                $this->recurseDescendants($member, $results);
            }
        }
    }


    /**
     * Evaluate condition against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param object $environment - Environment
     * @return mixed Evaluated input data
     */
    private function evaluateCondition($expr, $input, $environment)
    {
        $result = null;

        $condition = $this->evaluate($expr->condition, $input, $environment);

        if (static::boolize($condition)) {
            $result = $this->evaluate($expr->then, $input, $environment);
        } elseif ($expr->_else !== null) {
            $result = $this->evaluate($expr->_else, $input, $environment);
        }

        return $result;
    }

    /**
     * Evaluate block against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param _Frame $environment - Environment
     * @return mixed Evaluated input data
     */
    private function evaluateBlock($expr, $input, $environment)
    {
        $result = null;

        // create a new frame to limit the scope of variable assignments
        // TODO: only do this if the post-parse stage has flagged this as required
        $frame = $this->createFrame($environment);

        // invoke each expression in turn, only return the result of the last one
        foreach ($expr->expressions as $ex) {
            $result = $this->evaluate($ex, $input, $frame);
        }

        return $result;
    }

    /**
     * Evaluate bind expression
     *
     * @param Symbol $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param _Frame $environment - Environment
     * @return mixed Evaluated input data
     */
    private function evaluateBindExpression(Symbol $expr, $input, _Frame $environment)
    {
        // The RHS is the expression to evaluate
        // The LHS is the name of the variable to bind to
        $value = $this->evaluate($expr->rhs, $input, $environment);

        // Bind the value to the variable name in the environment
        $environment->bind((string) $expr->lhs->value, $value);

        return $value;
    }

    /**
     * Prepare a regex
     *
     * @param Symbol $expr - expression containing regex
     * @return mixed Higher-order object representing prepared regex
     */
    private function evaluateRegex(Symbol $expr)
    {
        // Note: in Java we just use the compiled regex Pattern
        // The apply functions need to take care to evaluate
        return $expr->value;
    }

    /**
     * Evaluate a function
     *
     * @param Symbol $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param _Frame $environment - Environment
     * @param mixed $applytoContext - Optional context to apply the function to
     * @return mixed Evaluated input data
     * @throws JException
     */
    private function evaluateFunction(Symbol $expr, $input, _Frame $environment, $applytoContext): mixed
    {

        //TODO: implement
        throw new Exception("function calling is not implemented");
    }


    public function evaluateVariable(Symbol $expr, object $input, _Frame $environment): mixed
    {
        $result = null;

        if ($expr->value === "") {
            // Empty string refers to context value
            if ($input instanceof JList && $input->outerWrapper) {
                $result = $input->get(0);
            } else {
                $result = $input;
            }
        } else {
            $result = $environment->lookup((string) $expr->value);
            if (Parser::$dbg ?? false) {
                echo "variable name=" . $expr->value . " val=" . print_r($result, true) . PHP_EOL;
            }
        }

        return $result;
    }

    public function evaluateLambda(Symbol $expr, object $input, _Frame $environment): Symbol
    {
        // create a closure-like object
        $procedure = new Symbol($this->parser);

        $procedure->_jsonata_lambda = true;
        $procedure->input = $input;
        $procedure->environment = $environment;
        $procedure->arguments = $expr->arguments;
        $procedure->signature = $expr->signature;
        $procedure->body = $expr->body;

        if ($expr->thunk === true) {
            $procedure->thunk = true;
        }

        // Optional: could add an 'apply' callable if needed
        // $procedure->apply = function(?Symbol $self, array $args = []) use ($procedure, $input, $environment) {
        //     return $this->apply($procedure, $args, $input, $self?->environment ?? $environment);
        // };

        return $procedure;
    }

    public function evaluatePartialApplication(Symbol $expr, object $input, _Frame $environment): mixed
    {
        $result = null;

        // evaluate the arguments
        $evaluatedArgs = [];
        for ($ii = 0; $ii < count($expr->arguments); $ii++) {
            $arg = $expr->arguments[$ii];
            if ($arg->type === "operator" && $arg->value === "?") {
                $evaluatedArgs[] = $arg;
            } else {
                $evaluatedArgs[] = $this->evaluate($arg, $input, $environment);
            }
        }

        // lookup the procedure
        $proc = $this->evaluate($expr->procedure, $input, $environment);

        if (
            $proc !== null && $expr->procedure->type === "path"
            && $environment->lookup((string) $expr->procedure->steps[0]->value) !== null
        ) {
            throw new JException(
                "T1007",
                $expr->position,
                $expr->procedure->steps[0]->value
            );
        }

        if (Functions::isLambda($proc)) {
            $result = $this->partialApplyProcedure($proc, $evaluatedArgs);
        } elseif (Utils::isFunction($proc)) {
            $result = $this->partialApplyNativeFunction($proc, $evaluatedArgs);
        } else {
            throw new JException(
                "T1008",
                $expr->position,
                $expr->procedure->type === "path"
                ? $expr->procedure->steps[0]->value
                : $expr->procedure->value
            );
        }

        return $result;
    }


    public function partialApplyProcedure(Symbol $proc, array $args): Symbol
    {
        // create a closure, bind the supplied parameters, return an object that takes remaining (?) parameters
        $env = $this->createFrame($proc->environment ?? $this->environment);

        $unboundArgs = [];
        $index = 0;

        foreach ($proc->arguments as $param) {
            $arg = $args[$index] ?? null;

            if ($arg === null || ($arg instanceof Symbol && $arg->type === "operator" && $arg->value === "?")) {
                $unboundArgs[] = $param;
            } else {
                $env->bind((string) $param->value, $arg);
            }
            $index++;
        }

        $procedure = new Symbol($this->parser); // using parser as in original Java
        $procedure->_jsonata_lambda = true;
        $procedure->input = $proc->input;
        $procedure->environment = $env;
        $procedure->arguments = $unboundArgs;
        $procedure->body = $proc->body;

        return $procedure;
    }


    public function partialApplyNativeFunction(_JFunction $_native, array $args): Symbol
    {
        // create a lambda object that wraps and invokes the native function
        $sigArgs = [];
        $partArgs = [];

        for ($i = 0; $i < $_native->getNumberOfArgs(); $i++) {
            $argName = '$' . chr(ord('a') + $i);
            $sigArgs[] = $argName;

            if ($i >= count($args) || $args[$i] === null) {
                $partArgs[] = $argName;
            } else {
                $partArgs[] = $args[$i];
            }
        }

        $body = 'function(' . implode(', ', $sigArgs) . ') { ';
        $body .= '$' . $_native->functionName . '(' . implode(', ', $sigArgs) . '); }';

        if (parser::$dbg ?? false) {
            echo "partial trampoline = " . $body . PHP_EOL;
        }

        // parse the body to AST
        $bodyAST = $this->parser->parse($body);

        // create partially applied procedure
        $partial = $this->partialApplyProcedure($bodyAST, $args);

        return $partial;
    }



    public function evaluateApplyExpression(Symbol $expr, object $input, _Frame $environment): mixed
    {
        $result = null;

        $lhs = $this->evaluate($expr->lhs, $input, $environment);

        if ($expr->rhs->type === "function") {
            // lhs expression as the first argument
            $result = $this->evaluateFunction($expr->rhs, $input, $environment, $lhs);
        } else {
            $func = $this->evaluate($expr->rhs, $input, $environment);

            if (!$this->isFunctionLike($func) && !$this->isFunctionLike($lhs)) {
                throw new JException(
                    "T2006",
                    $expr->position,
                    $func
                );
            }

            if ($this->isFunctionLike($lhs)) {
                // Object chaining (func1 ~> func2)
                // λ($f, $g) { λ($x){ $g($f($x)) } }
                $chain = $this->evaluate(static::chainAST(), null, $environment);
                $args = [$lhs, $func];
                $result = $this->apply($chain, $args, null, $environment);
            } else {
                $args = [$lhs];
                $result = $this->apply($func, $args, null, $environment);
            }
        }

        return $result;
    }


    public function isFunctionLike(object $o): bool
    {
        //TODO: create pattern class
        // return Utils::isFunction($o) || Functions::isLambda($o) || $o instanceof Pattern;
        return Utils::isFunction($o) || Functions::isLambda($o);
    }

    public function apply(object $proc, object|array $args, ?object $input, object $environment): mixed
    {
        //TODO: implement
        throw new Exception("function calling is not implemented");
    }



    public function evaluateTransformExpression(Symbol $expr, object $input, _Frame $environment): _JFunction
    {
        $transformer = new TransformCallable($expr, $environment, $this);
        $jFunction = new _JFunction($transformer, "<(oa):o>");
        return $jFunction;
    }

    public function evaluateFilter(object $_predicate, object $input, _Frame $environment): array|JList
    {
        $predicate = $_predicate; // Symbol
        $results = Utils::createSequence();

        if ($input instanceof JList && $input->tupleStream) {
            $results->tupleStream = true;
        }

        if (!is_array($input)) {
            $input = Utils::createSequence($input);
        }

        if ($predicate->type === "number") {
            $index = (int) $predicate->value; // round down
            if ($index < 0) {
                $index = count($input) + $index;
            }
            $item = $index < count($input) ? $input[$index] : null;
            if ($item !== null) {
                if (is_array($item)) {
                    $results = $item;
                } else {
                    $results[] = $item;
                }
            }
        } else {
            for ($index = 0; $index < count($input); $index++) {
                $item = $input[$index];
                $context = $item;
                $env = $environment;

                if ($input instanceof JList && $input->tupleStream) {
                    $context = $item['@'] ?? null;
                    $env = $this->createFrameFromTuple($environment, $item);
                }

                $res = $this->evaluate($predicate, $context, $env);

                if (Utils::isNumeric($res)) {
                    $res = Utils::createSequence($res);
                }

                if (Utils::isArrayOfNumbers($res)) {
                    foreach ($res as $ires) {
                        $ii = (int) $ires; // Math.floor equivalent
                        if ($ii < 0) {
                            $ii = count($input) + $ii;
                        }
                        if ($ii === $index) {
                            $results[] = $item;
                        }
                    }
                } elseif (static::boolize($res)) { // truthy
                    $results[] = $item;
                }
            }
        }

        return $results;
    }

    public function evaluateGroupExpression(Symbol $expr, object $_input, _Frame $environment): array
    {
        $result = [];
        $groups = [];
        $reduce = ($_input instanceof JList) && $_input->tupleStream;

        if (!is_array($_input)) {
            $_input = Utils::createSequence($_input);
        }

        $input = $_input;

        // if input is empty, add null to enable literal JSON object generation
        if (empty($input)) {
            $input[] = null;
        }

        foreach ($input as $item) {
            $env = $reduce ? $this->createFrameFromTuple($environment, $item) : $environment;

            foreach ($expr->lhsObject as $pairIndex => $pair) {
                $key = $this->evaluate($pair[0], $reduce ? ($item['@'] ?? null) : $item, $env);

                // key must be string
                if ($key !== null && !is_string($key)) {
                    throw new JException("T1003", $expr->position, $key);
                }

                if ($key !== null) {
                    $entry = new _GroupEntry();
                    $entry->data = $item;
                    $entry->exprIndex = $pairIndex;

                    if (isset($groups[$key])) {
                        // key already exists
                        if ($groups[$key]->exprIndex !== $pairIndex) {
                            throw new JException("D1009", $expr->position, $key);
                        }
                        $groups[$key]->data = Functions::append($groups[$key]->data, $item);
                    } else {
                        $groups[$key] = $entry;
                    }
                }
            }
        }

        $idx = 0;
        foreach ($groups as $key => $entry) {
            $context = $entry->data;
            $env = $environment;

            if ($reduce) {
                $tuple = $this->reduceTupleStream($entry->data);
                $context = $tuple['@'] ?? null;
                unset($tuple['@']);
                $env = $this->createFrameFromTuple($environment, $tuple);
            }

            $env->isParallelCall = $idx > 0;

            $res = $this->evaluate($expr->lhsObject[$entry->exprIndex][1], $context, $env);
            if ($res !== null) {
                $result[$key] = $res;
            }

            $idx++;
        }

        return $result;
    }

    public function reduceTupleStream(mixed $_tupleStream): array|object
    {
        if (!is_array($_tupleStream)) {
            return $_tupleStream;
        }

        /** @var array<int,array<string,mixed>> $tupleStream */
        $tupleStream = $_tupleStream;

        $result = $tupleStream[0] ?? [];

        for ($ii = 1; $ii < count($tupleStream); $ii++) {
            $el = $tupleStream[$ii];
            foreach ($el as $prop => $value) {
                $result[$prop] = Functions::append($result[$prop] ?? null, $value);
            }
        }

        return $result;
    }


}
