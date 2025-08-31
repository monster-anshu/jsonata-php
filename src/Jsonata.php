<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

use Exception;

class Jsonata
{
    public readonly Parser $parser;
    public ?_Frame $environment = null;

    public static ?_Frame $staticFrame = null;
     // equivalent to: static Frame staticFrame;
    public ?Symbol $ast;

    private ?array $errors = null;
    private bool $validateInput = true;

    private function createFrame(?_Frame $frame = null): _Frame
    {
        if ($frame instanceof \Monster\JsonataPhp\_Frame) {
            return new _Frame($frame);

        }

        return new _Frame(null);
    }

    private static ?Symbol $chainAST = null;

    private function chainAST(): Symbol
    {
        if (!self::$chainAST instanceof \Monster\JsonataPhp\Symbol) {
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

    public function __construct(string $expr)
    {
        $this->parser = new Parser();
        $this->ast = $this->parser->parse($expr);
        $this->errors = $this->ast->errors;
        // The Java equivalent for `ast.errors = null`
        unset($this->ast->errors);
        $this->environment = $this->createFrame(); // microtime(true) provides a float with more precision than milliseconds
        Jsonata::$jsonata = $this;
        // The Java comments for 'now' and 'millis' show how to bind functions.
        // In PHP, you would use a similar function registration mechanism.
        // (This part is illustrative and depends on the rest of the Jsonata library)
        // $this->environment->bind("now", new FunctionDefinition(...));
        // $this->environment->bind("millis", new FunctionDefinition(...));
    }

    public static function defineFunction(string $func, string $signature)
    {
        return self::defineFunctionWithImpl($func, $signature, $func);
    }

    public static function defineFunctionWithImpl(string $func, string $signature, string $funcImplMethod)
    {
        $jFunction = new _JFunction($func, $signature, Functions::class, $funcImplMethod);
        self::$staticFrame->bind($func, $jFunction);
        return $jFunction;
    }


    public static function registerFunctions()
    {
        self::defineFunction("sum", "<a<n>:n>");
        self::defineFunction("count", "<a:n>");
        self::defineFunction("max", "<a<n>:n>");
        self::defineFunction("min", "<a<n>:n>");
        self::defineFunction("average", "<a<n>:n>");
        self::defineFunction("string", "<x-b?:s>");
        self::defineFunction("substring", "<s-nn?:s>");
        self::defineFunction("substringBefore", "<s-s:s>");
        self::defineFunction("substringAfter", "<s-s:s>");
        self::defineFunction("lowercase", "<s-:s>");
        self::defineFunction("uppercase", "<s-:s>");
        self::defineFunction("length", "<s-:n>");
        self::defineFunction("trim", "<s-:s>");
        self::defineFunction("pad", "<s-ns?:s>");
        self::defineFunction("match", "<s-f<s:o>n?:a<o>>");
        self::defineFunction("contains", "<s-(sf):b>");
        self::defineFunction("replace", "<s-(sf)(sf)n?:s>");
        self::defineFunction("split", "<s-(sf)n?:a<s>>");
        self::defineFunction("join", "<a<s>s?:s>");
        self::defineFunction("formatNumber", "<n-so?:s>");
        self::defineFunction("formatBase", "<n-n?:s>");
        self::defineFunction("formatInteger", "<n-s:s>");
        self::defineFunction("parseInteger", "<s-s:n>");
        self::defineFunction("number", "<(nsb)-:n>");
        self::defineFunction("floor", "<n-:n>");
        self::defineFunction("ceil", "<n-:n>");
        self::defineFunction("round", "<n-n?:n>");
        self::defineFunction("abs", "<n-:n>");
        self::defineFunction("sqrt", "<n-:n>");
        self::defineFunction("power", "<n-n:n>");
        self::defineFunction("random", "<:n>");
        self::defineFunction("boolean", "<x-:b>");
        self::defineFunction("not", "<x-:b>");
        self::defineFunction("map", "<af>");
        self::defineFunction("zip", "<a+>");
        self::defineFunction("filter", "<af>");
        self::defineFunction("single", "<af?>");
        self::defineFunction("reduce", "<afj?:j>");
        self::defineFunction("sift", "<o-f?:o>");
        self::defineFunction("keys", "<x-:a<s>>");
        self::defineFunction("lookup", "<x-s:x>");
        self::defineFunction("append", "<xx:a>");
        self::defineFunction("exists", "<x:b>");
        self::defineFunction("spread", "<x-:a<o>>");
        self::defineFunction("merge", "<a<o>:o>");
        self::defineFunction("reverse", "<a:a>");
        self::defineFunction("each", "<o-f:a>");
        self::defineFunction("error", "<s?:x>");
        self::defineFunction("assert", "<bs?:x>");
        self::defineFunction("type", "<x:s>");
        self::defineFunction("sort", "<af?:a>");
        self::defineFunction("shuffle", "<a:a>");
        self::defineFunction("distinct", "<x:x>");
        self::defineFunction("base64encode", "<s-:s>");
        self::defineFunction("base64decode", "<s-:s>");
        self::defineFunction("encodeUrlComponent", "<s-:s>");
        self::defineFunction("encodeUrl", "<s-:s>");
        self::defineFunction("decodeUrlComponent", "<s-:s>");
        self::defineFunction("decodeUrl", "<s-:s>");
        self::defineFunction("eval", "<sx?:x>");
        self::defineFunction("toMillis", "<s-s?:n>");
        self::defineFunction("fromMillis", "<n-s?s?:s>");
        self::defineFunction("clone", "<(oa)-:o>");

        self::defineFunction("now", "<s?s?:s>");
        self::defineFunction("millis", "<:n>");
    }


    private static Jsonata $jsonata;

    public static function current()
    {
        //TODO: check this
        return self::$jsonata;
    }


    // A simple wrapper to call the main evaluate method.
    public function evaluate(mixed $input)
    {
        $result = $this->evaluateWithBindings($input, null);
        if (Utils::isArray($result)) {
            return (array) $result;
        }

        return $result;
    }

    /*
     * Main evaluation method.
     * @param mixed $input The data to evaluate against.
     * @param Frame|null $bindings An optional Frame object with variable bindings.
     * @return mixed The result of the evaluation.
     * @throws JException If a syntax error exists or an evaluation error occurs.
     */
    public function evaluateWithBindings($input, $bindings)
    {
        // Throw if the expression compiled with syntax errors.
        if (!is_null($this->errors)) {
            throw new JException("S0500", 0);
        }

        /**
         * @var ?_Frame
         */
        $exec_env = null;
        if (!is_null($bindings)) {
            // The variable bindings have been passed in - create a frame to hold these.
            $exec_env = $this->createFrame($this->environment);
            foreach ($bindings->bindings as $key => $value) {
                $exec_env->bind($key, $value);
            }
        } else {
            $exec_env = $this->environment;
        }

        // Put the input document into the environment as the root object.
        $exec_env->bind('$', $input);
        $exec_env = Jsonata::$staticFrame;

        // If the input is a JSON array, then wrap it in a singleton sequence
        // so it gets treated as a single input.
        if (Utils::isArray($input) && !Utils::isSequence($input)) {
            $input = Utils::createSequence($input);
            $input->outerWrapper = true;
        }

        if ($this->validateInput) {
            Functions::validateInput($input);
        }

        try {
            $it = $this->evaluateAst($this->ast, $input, $exec_env);
            return Utils::convertNulls($it);
        } catch (Exception $exception) {
            // Re-throw the exception after any necessary side-effects on it.
            $this->populateMessage($exception);
            throw $exception;
        }
    }

    /**
     * Evaluate expression against input data
     *
     * @param Symbol $symbol JSONata expression
     * @param mixed $input Input data to evaluate against
     * @param _Frame|null $frame Environment
     * @return mixed Evaluated input data
     */
    public function evaluateAst(Symbol $symbol, mixed $input, ?_Frame $frame = null): mixed
    {
        // Thread safety:
        // Make sure each evaluate is executed on an instance per thread
        return $this->getPerThreadInstance()->_evaluate($symbol, $input, $frame);
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

    public function populateMessage(Exception $exception): Exception
    {
        // The original Java code has commented-out logic, so this simply returns the exception.
        // If the commented logic were to be ported, it would use regular expressions
        // and a predefined errorCodes array.
        return $exception;
    }

    /**
     * Placeholder for the actual evaluator.
     * Equivalent to Java's private _evaluate(...)
     */
    private function _evaluate(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        $result = null;
        $this->environment = $frame;

        if ($this->parser->dbg) {
            echo "eval expr=" . json_encode($symbol) . " type=" . ($symbol->type ?? "null") . PHP_EOL;
        }

        $entryCallback = $frame->lookup("__evaluate_entry");
        if ($entryCallback !== null && $entryCallback instanceof _EntryCallback) {
            $entryCallback->callback($symbol, $input, $frame);
        }

        if ($symbol->type !== null && $symbol->type !== '' && $symbol->type !== '0') {
            switch ($symbol->type) {
                case "path":
                    $result = $this->evaluatePath($symbol, $input, $frame);
                    break;
                case "binary":
                    $result = $this->evaluateBinary($symbol, $input, $frame);
                    break;
                case "unary":
                    $result = $this->evaluateUnary($symbol, $input, $frame);
                    break;
                case "name":
                    $result = $this->evaluateName($symbol, $input);
                    if ($this->parser->dbg) {
                        echo "evalName " . json_encode($result) . PHP_EOL;
                    }

                    break;
                case "string":
                case "number":
                case "value":
                    $result = $this->evaluateLiteral($symbol);
                    break;
                case "wildcard":
                    $result = $this->evaluateWildcard($symbol, $input);
                    break;
                case "descendant":
                    $result = $this->evaluateDescendants($input);
                    break;
                case "parent":
                    $result = $frame->lookup($symbol->slot->label ?? "");
                    break;
                case "condition":
                    $result = $this->evaluateCondition($symbol, $input, $frame);
                    break;
                case "block":
                    $result = $this->evaluateBlock($symbol, $input, $frame);
                    break;
                case "bind":
                    $result = $this->evaluateBindExpression($symbol, $input, $frame);
                    break;
                case "regex":
                    $result = $this->evaluateRegex($symbol);
                    break;
                case "function":
                    $result = $this->evaluateFunction($symbol, $input, $frame, Utils::$none);
                    break;
                case "variable":
                    $result = $this->evaluateVariable($symbol, $input, $frame);
                    break;
                case "lambda":
                    $result = $this->evaluateLambda($symbol, $input, $frame);
                    break;
                case "partial":
                    $result = $this->evaluatePartialApplication($symbol, $input, $frame);
                    break;
                case "apply":
                    $result = $this->evaluateApplyExpression($symbol, $input, $frame);
                    break;
                case "transform":
                    $result = $this->evaluateTransformExpression($symbol, $frame);
                    break;
            }
        }

        if ($symbol->predicate !== null && $symbol->predicate !== []) {
            foreach ($symbol->predicate as $pred) {
                $result = $this->evaluateFilter($pred->expr, $result, $frame);
            }
        }

        if (($symbol->type ?? "") !== "path" && $symbol->group instanceof \Monster\JsonataPhp\Symbol) {
            $result = $this->evaluateGroupExpression($symbol->group, $result, $frame);
        }

        $exitCallback = $frame->lookup("__evaluate_exit");
        if ($exitCallback !== null && $exitCallback instanceof _ExitCallback) {
            $exitCallback->callback($symbol, $input, $frame, $result);
        }

        // mangle result (list of 1 element -> 1 element, empty list -> null)
        if ($result !== null && Utils::isSequence($result) && !$result->tupleStream) {
            /** @var JList $result */
            if ($symbol->keepArray ?? false) {
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
     * @param Symbol $symbol JSONata expression
     * @param mixed  $input       Input data to evaluate against
     * @param _Frame $frame Environment
     * @return mixed              Evaluated input data
     */
    private function evaluatePath(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        // expr is an array of steps
        // if the first step is a variable reference ($...), including root reference ($$),
        //   then the path is absolute rather than relative
        if ($input instanceof JList && $symbol->steps[0]->type !== "variable") {
            $inputSequence = $input;
        } else {
            // if input is not an array, make it so
            $inputSequence = Utils::createSequence($input);
        }

        $resultSequence = null;
        $isTupleStream = false;
        $tupleBindings = null;
        // evaluate each step in turn
        foreach ($symbol->steps as $ii => $step) {
            if ($step->tuple !== null) {
                $isTupleStream = true;
            }

            // if the first step is an explicit array constructor,
            // then just evaluate that (i.e. don’t iterate over a context array)
            if ($ii === 0 && $step->consarray) {
                $resultSequence = $this->evaluateAst($step, $inputSequence, $frame);
            } elseif ($isTupleStream) {
                $tupleBindings = $this->evaluateTupleStep($step, $inputSequence, $tupleBindings ?? [], $frame);
            } else {
                $resultSequence = $this->evaluateStep(
                    $step,
                    $inputSequence,
                    $frame,
                    $ii === count($symbol->steps) - 1
                );

            }

            if (!$isTupleStream && ($resultSequence === null || count($resultSequence) === 0)) {
                break;
            }

            if ($step->focus === null) {
                $inputSequence = $resultSequence;
            }
        }

        if ($isTupleStream) {
            if ($symbol->tuple !== null) {
                // tuple stream is carrying ancestry information - keep this
                $resultSequence = $tupleBindings;
            } else {
                $resultSequence = Utils::createSequence();
                foreach ($tupleBindings as $tupleBinding) {
                    $resultSequence->append($tupleBinding["@"] ?? null);
                }
            }
        }

        if ($symbol->keepSingletonArray) {
            // If we only got an array, wrap into JList so we can set the keepSingleton flag
            if (!($resultSequence instanceof JList)) {
                $resultSequence = new JList($resultSequence);
            }

            // if the array is explicitly constructed in the expression
            // and marked to promote singleton sequences to array
            if ($resultSequence instanceof JList && $resultSequence->cons && !$resultSequence->sequence) {
                $resultSequence = Utils::createSequence($resultSequence);
            }

            $resultSequence->keepSingleton = true;
        }

        if ($symbol->group instanceof \Monster\JsonataPhp\Symbol) {
            $resultSequence = $this->evaluateGroupExpression(
                $symbol->group,
                $isTupleStream ? $tupleBindings : $resultSequence,
                $frame
            );
        }

        return $resultSequence;
    }

    /**
     * Evaluate a step within a path
     *
     * @param Symbol $symbol JSONata expression
     * @param array|JList $input     Input data to evaluate against
     * @param array|JList|null $tupleBindings The tuple stream
     * @param _Frame $frame Environment
     * @return array|JList           Evaluated input data
     */
    private function evaluateTupleStep(Symbol $symbol, array|JList|null $input, array|JList|null $tupleBindings, _Frame $frame): array|JList
    {
        $result = null;

        // Handle "sort" expressions
        if ($symbol->type === "sort") {
            if ($tupleBindings !== null) {
                $result = $this->evaluateSortExpression($symbol, $tupleBindings, $frame);
            } else {
                $sorted = $this->evaluateSortExpression($symbol, $input, $frame);

                $result = new JList();
                $result->tupleStream = true;

                foreach ($sorted as $ss => $val) {
                    $binding = [
                        '@' => $val,
                        $symbol->index => $ss,
                    ];
                    $result->append($binding);
                }
            }

            if ($symbol->stages !== null) {
                $result = $this->evaluateStages($symbol->stages, $result, $frame);
            }

            return $result;
        }

        // Otherwise, build a tuple stream
        $result = new JList();
        $result->tupleStream = true;

        $stepEnv = $frame;

        // If no tuple bindings, wrap each input item in ["@" => item]
        if ($tupleBindings === null) {
            $tupleBindings = array_values(
                array_map(
                    fn ($item) => ['@' => $item],
                    array_filter($input, fn ($item) => $item !== null)
                )
            );
        }

        foreach ($tupleBindings as $tupleBinding) {
            $stepEnv = $this->createFrameFromTuple($frame, $tupleBinding);

            $_res = $this->evaluateAst($symbol, $tupleBinding['@'], $stepEnv);

            if ($_res !== null) {
                $res = Utils::isArray($_res) ? $_res : [$_res];

                foreach ($res as $bb => $val) {
                    $tuple = $tupleBinding; // clone

                    if ($_res instanceof JList && $_res->tupleStream) {
                        // merge tupleStream result
                        $tuple = array_merge($tuple, $_res[$bb]);
                    } else {
                        if ($symbol->focus !== null) {
                            $tuple[$symbol->focus] = $val;
                            $tuple['@'] = $tupleBinding['@'];
                        } else {
                            $tuple['@'] = $val;
                        }

                        if ($symbol->index !== null) {
                            $tuple[$symbol->index] = $bb;
                        }

                        if ($symbol->ancestor instanceof \Monster\JsonataPhp\Symbol) {
                            $tuple[$symbol->ancestor->label] = $tupleBinding['@'];
                        }
                    }

                    $result->append($tuple);
                }
            }
        }

        if ($symbol->stages !== null) {
            $result = $this->evaluateStages($symbol->stages, $result, $frame);
        }

        return $result;
    }

    /**
     * Evaluate a step within a path
     *
     * @param Symbol $symbol JSONata expression
     * @param array|JList $input Input data to evaluate against
     * @param _Frame $frame Environment
     * @param bool $lastStep Flag the last step in a path
     * @return mixed Evaluated input data
     */
    private function evaluateStep(Symbol $symbol, array|JList $input, _Frame $frame, bool $lastStep): mixed
    {
        $result = null;

        if ($symbol->type === "sort") {
            $result = $this->evaluateSortExpression($symbol, $input, $frame);

            if ($symbol->stages !== null) {
                $result = $this->evaluateStages($symbol->stages, $result, $frame);
            }

            return $result;
        }

        $result = Utils::createSequence();

        foreach ($input as $item) {
            $res = $this->evaluateAst($symbol, $item, $frame);
            if ($symbol->stages !== null) {
                foreach ($symbol->stages as $stage) {
                    $res = $this->evaluateFilter($stage->expr, $res, $frame);
                }
            }

            if ($res !== null) {
                $result->append($res);
            }
        }

        $resultSequence = Utils::createSequence();

        // special case when last step
        if (
            $lastStep &&
            count($result) === 1 &&
            Utils::isArray($result->get(0)) &&
            !Utils::isSequence($result->get(0))
        ) {
            $resultSequence = $result[0];
        } else {
            // flatten the sequence
            foreach ($result as $res) {
                if (!Utils::isArray($res) || ($res->cons ?? 0)) {
                    // it's not an array - just push into the result sequence
                    $resultSequence->append($res);
                } else {
                    // res is a sequence - flatten it into the parent sequence
                    foreach ($res as $re) {
                        $resultSequence->append($re);
                    }
                }
            }
        }

        return $resultSequence;
    }


    /**
     * Sort / order-by operator
     *
     * @param Symbol $symbol AST for operator
     * @param array|JList $input Input data to evaluate against
     * @param _Frame $frame Environment
     * @return array|JList Ordered sequence
     * @throws JException
     */
    private function evaluateSortExpression(Symbol $symbol, array|JList $input, _Frame $frame): array|JList
    {
        // evaluate the lhs, then sort the results in order according to rhs expression
        $lhs = $input;
        $isTupleSort = $input instanceof JList && $input->tupleStream;

        // comparator closure
        $comparator = function ($a, $b) use ($symbol, $frame, $isTupleSort) {
            $comp = 0;

            foreach ($symbol->terms as $term) {
                if ($comp !== 0) {
                    break;
                }

                // ---- evaluate sort term in context of $a ----
                $context = $a;
                $env = $frame;
                if ($isTupleSort) {
                    $context = $a['@'] ?? null;
                    $env = $this->createFrameFromTuple($frame, $a);
                }

                $aa = $this->evaluateAst($term->expression, $context, $env);

                // ---- evaluate sort term in context of $b ----
                $context = $b;
                $env = $frame;
                if ($isTupleSort) {
                    $context = $b['@'] ?? null;
                    $env = $this->createFrameFromTuple($frame, $b);
                }

                $bb = $this->evaluateAst($term->expression, $context, $env);

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
                    !is_string($aa) && !is_numeric($aa) ||
                    !is_string($bb) && !is_numeric($bb)
                ) {
                    throw new JException("T2008", $symbol->position, $aa, $bb);
                }

                // must be same type
                $sameType = false;
                if (is_numeric($aa) && is_numeric($bb)) {
                    $sameType = true;
                } elseif (gettype($aa) === gettype($bb)) {
                    $sameType = true;
                }

                if (!$sameType) {
                    throw new JException("T2007", $symbol->position, $aa, $bb);
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
        return Functions::sort((array) $lhs, $comparator);
    }


    /**
     * Evaluate pipeline stages
     *
     * @param Symbol[] $stages
     * @throws JException
     */
    private function evaluateStages(array $stages, mixed $input, _Frame $frame): mixed
    {
        $result = $input;

        foreach ($stages as $stage) {
            switch ($stage->type) {
                case "filter":
                    $result = $this->evaluateFilter($stage->expr, $result, $frame);
                    break;

                case "index":
                    if (Utils::isArray($result)) {
                        foreach ($result as $ee => &$tuple) {
                            $tuple[strval($stage->value)] = $ee;
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
     * @param Symbol $symbol JSONata expression
     * @param mixed $input Input data
     * @param _Frame $frame Environment
     * @throws JException
     */
    private function evaluateBinary(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        $lhs = $this->evaluateAst($symbol->lhs, $input, $frame);
        $op = (string) $symbol->value;

        if ($op === "and" || $op === "or") {
            // defer evaluation of RHS to allow short-circuiting
            $evalrhs = (fn () => $this->evaluateAst($symbol->rhs, $input, $frame));

            try {
                return $this->evaluateBooleanExpression($lhs, $evalrhs, $op);
            } catch (\Throwable $err) {
                if (!($err instanceof JException)) {
                    throw new JException("Unexpected", $symbol->position);
                }

                throw $err;
            }
        }

        $rhs = $this->evaluateAst($symbol->rhs, $input, $frame);

        return match ($op) {
            "+", "-", "*", "/", "%" => $this->evaluateNumericExpression($lhs, $rhs, $op),
            "=", "!=" => $this->evaluateEqualityExpression($lhs, $rhs, $op),
            "<", "<=", ">", ">=" => $this->evaluateComparisonExpression($lhs, $rhs, $op),
            "&" => $this->evaluateStringConcat($lhs, $rhs),
            ".." => $this->evaluateRangeExpression($lhs, $rhs),
            "in" => $this->evaluateIncludesExpression($lhs, $rhs),
            default => throw new JException("Unexpected operator " . $op, $symbol->position),
        };
    }


    /**
     * Evaluate boolean expression against input data
     *
     * @param mixed    $lhs      LHS value
     * @param callable $evalrhs  Function to evaluate RHS value
     * @param string   $op       Operator ("and" / "or")
     * @throws \Exception
     */
    private function evaluateBooleanExpression(mixed $lhs, callable $evalrhs, string $op): bool
    {
        $lBool = static::boolize($lhs);

        return match ($op) {
            // RHS is evaluated only if needed
            "and" => $lBool && static::boolize($evalrhs()),
            // RHS is evaluated only if needed
            "or" => $lBool || static::boolize($evalrhs()),
            default => throw new \InvalidArgumentException('Unsupported boolean operator: ' . $op),
        };
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
    private function evaluateNumericExpression(mixed $lhs, mixed $rhs, string $op): mixed
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
    private function evaluateRangeExpression(mixed $lhs, mixed $rhs): ?array
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

        if (!Utils::isArray($rhs)) {
            $rhs = [$rhs];
        }

        foreach ($rhs as $rh) {
            $tmp = $this->evaluateEqualityExpression($lhs, $rh, "=");
            if ($tmp) {
                $result = true;
                break;
            }
        }

        return $result;
    }


    /**
     * Evaluate unary expression against input data
     * @throws JException
     */
    private function evaluateUnary(Symbol $symbol, mixed $input, _Frame $frame)
    {
        $result = null;

        switch ((string) $symbol->value) {
            case "-":
                $result = $this->evaluateAst($symbol->expression, $input, $frame);
                if ($result === null) {
                    $result = null;
                } elseif (Utils::isNumeric($result)) {
                    $result = Utils::convertNumber(-((float) $result));
                } else {
                    throw new JException(
                        "D1002",
                        $symbol->position,
                        $symbol->value,
                        $result
                    );
                }

                break;

            case "[":
                // array constructor - evaluate each item
                $result = new JList(); // []
                $idx = 0;
                foreach ($symbol->expressions as $item) {
                    $frame->isParallelCall = $idx > 0;
                    $value = $this->evaluateAst($item, $input, $frame);
                    if ($value !== null) {
                        if ((string) $item->value === "[") {
                            $result->append($value);
                        } else {
                            $result = Functions::append($result, $value);
                        }
                    }

                    ++$idx;
                }

                if ($symbol->consarray) {
                    if (!($result instanceof JList)) {
                        $result = new JList($result);
                    }

                    $result->cons = true;
                }

                break;

            case "{":
                // object constructor - apply grouping
                $result = $this->evaluateGroupExpression($symbol, $input, $frame);
                break;
        }

        return $result;
    }

    /**
     * Evaluate name object against input data
     *
     * @param object $expr - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @return mixed Evaluated input data
     */
    private function evaluateName($expr, $input)
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

        if ($input !== null && Utils::isAssoc($input)) {
            // input is a map (associative array in PHP)
            foreach ($input as $value) {
                if (Utils::isArray($value)) {
                    $value = $this->flatten($value, null);
                    $results = Functions::append($results, $value);
                } else {
                    $results->append($value);
                }
            }
        } elseif (Utils::isArray($input) || $input instanceof JList) {
            // input is a list
            foreach ($input as $value) {
                if (Utils::isArray($value)) {
                    $value = $this->flatten($value, null);
                    $results = Functions::append($results, $value);
                } elseif (Utils::isAssoc($value)) {
                    // recursive call for nested map
                    $results = array_merge((array) $results, $this->evaluateWildcard($expr, $value));
                } else {
                    $results->append($value);
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

        if (Utils::isArray($arg)) {
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
     * @param mixed $input - Input data to evaluate against
     * @return mixed Evaluated input data
     */
    private function evaluateDescendants($input)
    {
        $result = null;
        $jList = Utils::createSequence();

        if ($input !== null) {
            // traverse all descendants of this object/array
            $this->recurseDescendants($input, $jList);

            $result = count($jList) === 1 ? $jList[0] : $jList;
        }

        return $result;
    }

    /**
     * Recurse through descendants
     *
     * @param mixed $input   Input data
     * @param JList &$jList Results (passed by reference)
     */
    private function recurseDescendants($input, JList &$jList): void
    {
        // this is the equivalent of //* in XPath
        if (!Utils::isArray($input)) {
            $jList[] = $input;
        }

        if (Utils::isArray($input)) {
            // treat as list/map — foreach works for both
            foreach ($input as $member) {
                $this->recurseDescendants($member, $jList);
            }
        } elseif (Utils::isAssoc($input)) {
            // treat as list/map — foreach works for both
            foreach (array_keys($input) as $key) {
                $this->recurseDescendants($input[$key], $jList);
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

        $condition = $this->evaluateAst($expr->condition, $input, $environment);

        if (static::boolize($condition)) {
            $result = $this->evaluateAst($expr->then, $input, $environment);
        } elseif ($expr->_else !== null) {
            $result = $this->evaluateAst($expr->_else, $input, $environment);
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
            $result = $this->evaluateAst($ex, $input, $frame);
        }

        return $result;
    }

    /**
     * Evaluate bind expression
     *
     * @param Symbol $symbol - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param _Frame $frame - Environment
     * @return mixed Evaluated input data
     */
    private function evaluateBindExpression(Symbol $symbol, $input, _Frame $frame)
    {
        // The RHS is the expression to evaluate
        // The LHS is the name of the variable to bind to
        $value = $this->evaluateAst($symbol->rhs, $input, $frame);

        // Bind the value to the variable name in the environment
        $frame->bind((string) $symbol->lhs->value, $value);

        return $value;
    }

    /**
     * Prepare a regex
     *
     * @param Symbol $symbol - expression containing regex
     * @return mixed Higher-order object representing prepared regex
     */
    private function evaluateRegex(Symbol $symbol)
    {
        // Note: in Java we just use the compiled regex Pattern
        // The apply functions need to take care to evaluate
        return $symbol->value;
    }

    /**
     * Evaluate a function
     *
     * @param Symbol $symbol - JSONata expression
     * @param mixed $input - Input data to evaluate against
     * @param _Frame $frame - Environment
     * @param mixed $applytoContext - Optional context to apply the function to
     * @return mixed Evaluated input data
     * @throws JException
     */
    public function evaluateFunction(Symbol $symbol, $input, _Frame $frame, $applytoContext)
    {
        $result = null;

        // create the procedure
        $proc = $this->_evaluate($symbol->procedure, $input, $frame);

        if ($proc === null && $symbol->procedure->type === "path" && $frame->lookup((string) $symbol->procedure->steps[0]->value) !== null) {
            // help the user out here if they simply forgot the leading $
            throw new JException(
                "T1005",
                $symbol->position,
                $symbol->procedure->steps[0]->value
            );
        }

        $evaluatedArgs = [];

        if ($applytoContext !== Utils::$none) {
            $evaluatedArgs[] = $applytoContext;
        }
        // eager evaluation - evaluate the arguments
        $counter = count($symbol->arguments);

        // eager evaluation - evaluate the arguments
        for ($jj = 0; $jj < $counter; ++$jj) {
            $arg = $this->_evaluate($symbol->arguments[$jj], $input, $frame);
            if (Utils::isFunction($arg) || Functions::isLambda($arg)) {
                // wrap this in a closure - not required in PHP, already callable
                $evaluatedArgs[] = $arg;
            } else {
                $evaluatedArgs[] = $arg;
            }
        }

        $procName = $symbol->procedure->type === "path"
            ? $symbol->procedure->steps[0]->value
            : $symbol->procedure->value;

        if ($proc === null) {
            throw new JException("T1006", $symbol->position, $procName);
        }

        try {
            if ($proc instanceof Symbol) {
                $proc->token = $procName;
                $proc->position = $symbol->position;
            }

            $result = $this-> apply($proc, $evaluatedArgs, $input, $frame);
        } catch (JException $jex) {
            throw $jex;
        } catch (Exception $err) {
            if (!($err instanceof \RuntimeException)) {
                throw new \RuntimeException($err->getMessage(), 0, $err);
            }

            throw $err;
        }

        return $result;
    }



    private function evaluateVariable(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        $result = null;

        if ($symbol->value === "") {
            // Empty string refers to context value
            $result = $input instanceof JList && $input->outerWrapper ? $input->get(0) : $input;
        } else {
            $result = $frame->lookup((string) $symbol->value);
            if (Parser::$dbg ?? false) {
                echo "variable name=" . $symbol->value . " val=" . print_r($result, true) . PHP_EOL;
            }
        }

        return $result;
    }

    private function evaluateLambda(Symbol $symbol, mixed $input, _Frame $frame): Symbol
    {
        // create a closure-like object
        $procedure = new Symbol($this->parser);

        $procedure->_jsonata_lambda = true;
        $procedure->input = $input;
        $procedure->environment = $frame;
        $procedure->arguments = $symbol->arguments;
        $procedure->signature = $symbol->signature;
        $procedure->body = $symbol->body;

        if ($symbol->thunk) {
            $procedure->thunk = true;
        }

        // Optional: could add an 'apply' callable if needed
        // $procedure->apply = function(?Symbol $self, array $args = []) use ($procedure, $input, $environment) {
        //     return $this->apply($procedure, $args, $input, $self?->environment ?? $environment);
        // };

        return $procedure;
    }

    private function evaluatePartialApplication(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        $result = null;

        // evaluate the arguments
        $evaluatedArgs = [];
        $counter = count($symbol->arguments);
        for ($ii = 0; $ii < $counter; ++$ii) {
            $arg = $symbol->arguments[$ii];
            $evaluatedArgs[] = $arg->type === "operator" && $arg->value === "?" ? $arg : $this->evaluateAst($arg, $input, $frame);
        }

        // lookup the procedure
        $proc = $this->evaluateAst($symbol->procedure, $input, $frame);

        if (
            $proc !== null && $symbol->procedure->type === "path"
            && $frame->lookup((string) $symbol->procedure->steps[0]->value) !== null
        ) {
            throw new JException(
                "T1007",
                $symbol->position,
                $symbol->procedure->steps[0]->value
            );
        }

        if (Functions::isLambda($proc)) {
            $result = $this->partialApplyProcedure($proc, $evaluatedArgs);
        } elseif (Utils::isFunction($proc)) {
            $result = $this->partialApplyNativeFunction($proc, $evaluatedArgs);
        } else {
            throw new JException(
                "T1008",
                $symbol->position,
                $symbol->procedure->type === "path"
                ? $symbol->procedure->steps[0]->value
                : $symbol->procedure->value
            );
        }

        return $result;
    }


    private function partialApplyProcedure(Symbol $symbol, array $args): Symbol
    {
        // create a closure, bind the supplied parameters, return an object that takes remaining (?) parameters
        $frame = $this->createFrame($symbol->environment ?? $this->environment);

        $unboundArgs = [];
        $index = 0;

        foreach ($symbol->arguments as $param) {
            $arg = $args[$index] ?? null;

            if ($arg === null || ($arg instanceof Symbol && $arg->type === "operator" && $arg->value === "?")) {
                $unboundArgs[] = $param;
            } else {
                $frame->bind((string) $param->value, $arg);
            }

            ++$index;
        }

        $procedure = new Symbol($this->parser); // using parser as in original Java
        $procedure->_jsonata_lambda = true;
        $procedure->input = $symbol->input;
        $procedure->environment = $frame;
        $procedure->arguments = $unboundArgs;
        $procedure->body = $symbol->body;

        return $procedure;
    }


    private function partialApplyNativeFunction(_JFunction $jFunction, array $args): Symbol
    {
        // create a lambda object that wraps and invokes the native function
        $sigArgs = [];
        $partArgs = [];

        for ($i = 0; $i < $jFunction->getNumberOfArgs(); ++$i) {
            $argName = '$' . chr(ord('a') + $i);
            $sigArgs[] = $argName;

            $partArgs[] = $i >= count($args) || $args[$i] === null ? $argName : $args[$i];
        }

        $body = 'function(' . implode(', ', $sigArgs) . ') { ';
        $body .= '$' . $jFunction->functionName . '(' . implode(', ', $sigArgs) . '); }';

        if (parser::$dbg ?? false) {
            echo "partial trampoline = " . $body . PHP_EOL;
        }

        // parse the body to AST
        $bodyAST = $this->parser->parse($body);

        // create partially applied procedure
        $symbol = $this->partialApplyProcedure($bodyAST, $args);

        return $symbol;
    }



    private function evaluateApplyExpression(Symbol $symbol, mixed $input, _Frame $frame): mixed
    {
        $result = null;

        $lhs = $this->evaluateAst($symbol->lhs, $input, $frame);

        if ($symbol->rhs->type === "function") {
            // lhs expression as the first argument
            $result = $this->evaluateFunction($symbol->rhs, $input, $frame, $lhs);
        } else {
            $func = $this->evaluateAst($symbol->rhs, $input, $frame);

            if (!$this->isFunctionLike($func) && !$this->isFunctionLike($lhs)) {
                throw new JException(
                    "T2006",
                    $symbol->position,
                    $func
                );
            }

            if ($this->isFunctionLike($lhs)) {
                // Object chaining (func1 ~> func2)
                // λ($f, $g) { λ($x){ $g($f($x)) } }
                $chain = $this->evaluateAst($this->chainAST(), null, $frame);
                $args = [$lhs, $func];
                $result = $this->apply($chain, $args, null, $frame);
            } else {
                $args = [$lhs];
                $result = $this->apply($func, $args, null, $frame);
            }
        }

        return $result;
    }


    private function isFunctionLike(mixed $o): bool
    {
        return Utils::isFunction($o) || Functions::isLambda($o) || $o instanceof _Pattern;
    }

    public function apply($proc, $args, $input, $environment)
    {
        $result = $this-> applyInner($proc, $args, $input, $environment);

        while (Functions::isLambda($result) && $result instanceof Symbol && $result->thunk) {
            // trampoline loop - this gets invoked as a result of tail-call optimization
            // the Object returned a tail-call thunk
            // unpack it, evaluate its arguments, and apply the tail call
            $next = $this->_evaluate($result->body->procedure, $result->input, $result->environment);

            if ($result->body->procedure->type === "variable" && $next instanceof Symbol) {
                // Java: not if JFunction
                $next->token = $result->body->procedure->value;
            }

            if ($next instanceof Symbol) { // Java: not if JFunction
                $next->position = $result->body->procedure->position;
            }

            $evaluatedArgs = [];
            $counter = count($result->body->arguments);
            for ($ii = 0; $ii < $counter; ++$ii) {
                $evaluatedArgs[] = $this-> _evaluate($result->body->arguments[$ii], $result->input, $result->environment);
            }

            $result = $this->applyInner($next, $evaluatedArgs, $input, $environment);
        }

        return $result;
    }


    /**
 * Apply procedure or function
 * @param mixed $proc Procedure
 * @param array $args Arguments
 * @param mixed $input Input
 * @param mixed $environment Environment
 * @return mixed Result of procedure
 */
    public function applyInner($proc, $args, $input, $environment)
    {
        //TODO: implement
        return null;
    }




    private function evaluateTransformExpression(Symbol $symbol, _Frame $frame): _JFunction
    {
        $transformCallable = new _TransformCallable($symbol, $frame, $this);
        return new _JFunction($transformCallable, "<(oa):o>");
    }

    private function evaluateFilter(object $_predicate, mixed $input, _Frame $frame): array|JList
    {
        $predicate = $_predicate; // Symbol
        $results = Utils::createSequence();

        if ($input instanceof JList && $input->tupleStream) {
            $results->tupleStream = true;
        }

        if (!Utils::isArray($input)) {
            $input = Utils::createSequence($input);
        }

        if ($predicate->type === "number") {
            $index = (int) $predicate->value; // round down
            if ($index < 0) {
                // count in from end of array
                $index = count($input) + $index;
            }

            $item = $input[$index] ?? null;
            if ($item !== null) {
                if (Utils::isArray($item)) {
                    $results = $item;
                } else {
                    $results[] = $item;
                }
            }
        } else {
            $counter = count($input);
            for ($index = 0; $index < $counter; ++$index) {
                $item = $input[$index];
                $context = $item;
                $env = $frame;

                if ($input instanceof JList && $input->tupleStream) {
                    $context = $item['@'] ?? null;
                    $env = $this->createFrameFromTuple($frame, $item);
                }

                $res = $this->evaluateAst($predicate, $context, $env);

                if (Utils::isNumeric($res)) {
                    $res = Utils::createSequence($res);
                }

                if (Utils::isArrayOfNumbers($res)) {
                    foreach ($res as $re) {
                        $ii = (int) $re; // Math.floor equivalent
                        if ($ii < 0) {
                            $ii = count($input) + $ii;
                        }

                        if ($ii === $index) {
                            $results->append($item);
                        }
                    }
                } elseif (static::boolize($res)) { // truthy
                    $results->append($item);
                }
            }
        }

        return $results;
    }

    private function evaluateGroupExpression(Symbol $symbol, mixed $_input, _Frame $frame): array
    {
        $result = [];
        $groups = [];
        $reduce = ($_input instanceof JList) && $_input->tupleStream;

        if (!Utils::isArray($_input)) {
            $_input = Utils::createSequence($_input);
        }

        $input = $_input;
        if (!($input instanceof JList)) {
            $input = new JList($input);
        }

        // if input is empty, add null to enable literal JSON object generation
        if (empty($input)) {
            $input->append(null);
        }

        foreach ($input as $item) {
            $env = $reduce ? $this->createFrameFromTuple($frame, $item) : $frame;

            foreach ($symbol->lhsObject as $pairIndex => $pair) {
                $key = $this->evaluateAst($pair[0], $reduce ? ($item['@'] ?? null) : $item, $env);

                // key must be string
                if ($key !== null && !is_string($key)) {
                    throw new JException("T1003", $symbol->position, $key);
                }

                if ($key !== null) {
                    $entry = new _GroupEntry();
                    $entry->data = $item;
                    $entry->exprIndex = $pairIndex;

                    if (isset($groups[$key])) {
                        // key already exists
                        if ($groups[$key]->exprIndex !== $pairIndex) {
                            throw new JException("D1009", $symbol->position, $key);
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
            $env = $frame;

            if ($reduce) {
                $tuple = $this->reduceTupleStream($entry->data);
                $context = $tuple['@'] ?? null;
                unset($tuple['@']);
                $env = $this->createFrameFromTuple($frame, $tuple);
            }

            $env->isParallelCall = $idx > 0;

            $res = $this->evaluateAst($symbol->lhsObject[$entry->exprIndex][1], $context, $env);
            if ($res !== null) {
                $result[$key] = $res;
            }

            ++$idx;
        }

        return $result;
    }

    private function reduceTupleStream(mixed $_tupleStream): array|object
    {
        if (!Utils::isArray($_tupleStream)) {
            return $_tupleStream;
        }

        /** @var array<int,array<string,mixed>> $tupleStream */
        $tupleStream = $_tupleStream;

        $result = $tupleStream[0] ?? [];
        $counter = count($tupleStream);

        for ($ii = 1; $ii < $counter; ++$ii) {
            $el = $tupleStream[$ii];
            foreach ($el as $prop => $value) {
                $result[$prop] = Functions::append($result[$prop] ?? null, $value);
            }
        }

        return $result;
    }




}

Jsonata::$staticFrame = new _Frame();
Jsonata::registerFunctions();
