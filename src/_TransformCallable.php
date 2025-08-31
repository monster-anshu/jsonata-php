<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _TransformCallable implements _JFunctionCallable
{
    // assuming the class containing evaluate*

    public function __construct(private readonly Symbol $expr, private readonly _Frame $environment, private readonly Jsonata $evaluator)
    {
    }

    public function call(mixed $input, array $args): mixed
    {
        $obj = $args[0] ?? null;

        if ($obj === null) {
            return null;
        }

        $result = Functions::functionClone($obj);

        $_matches = $this->evaluator->evaluateAst($this->expr->pattern, $result, $this->environment);
        if ($_matches !== null) {
            if (!is_array($_matches)) {
                $_matches = [$_matches];
            }
            foreach ($_matches as &$match) {
                $update = $this->evaluator->evaluateAst($this->expr->update, $match, $this->environment);

                if ($update !== null) {
                    if (!is_array($update)) {
                        throw new JException("T2011", $this->expr->update->position, $update);
                    }
                    foreach ($update as $prop => $value) {
                        if (is_array($match)) {
                            $match[$prop] = $value;
                        }
                    }
                }

                if ($this->expr->delete !== null) {
                    $deletions = $this->evaluator->evaluateAst($this->expr->delete, $match, $this->environment);
                    $val = $deletions;
                    if ($deletions !== null) {
                        if (!is_array($deletions)) {
                            $deletions = [$deletions];
                        }
                        if (!Utils::isArrayOfStrings($deletions)) {
                            throw new JException("T2012", $this->expr->delete->position, $val);
                        }
                        foreach ($deletions as $key) {
                            if (is_array($match)) {
                                unset($match[$key]);
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
}
