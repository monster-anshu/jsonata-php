<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _TransformCallable implements _JFunctionCallable
{
    /**
     * @readonly
     * @var \Monster\JsonataPhp\Symbol
     */
    private $symbol;
    /**
     * @readonly
     * @var \Monster\JsonataPhp\_Frame
     */
    private $frame;
    /**
     * @readonly
     * @var \Monster\JsonataPhp\Jsonata
     */
    private $jsonata;
    // assuming the class containing evaluate*

    public function __construct(Symbol $symbol, _Frame $frame, Jsonata $jsonata)
    {
        $this->symbol = $symbol;
        $this->frame = $frame;
        $this->jsonata = $jsonata;
    }

    /**
     * @param mixed $input
     * @param mixed[] $args
     * @return mixed
     */
    public function call($input, $args)
    {
        $obj = $args[0] ?? null;

        if ($obj === null) {
            return null;
        }

        $result = Functions::functionClone($obj);

        $_matches = $this->jsonata->evaluateAst($this->symbol->pattern, $result, $this->frame);
        if ($_matches !== null) {
            if (!is_array($_matches)) {
                $_matches = [$_matches];
            }

            foreach ($_matches as &$_match) {
                $update = $this->jsonata->evaluateAst($this->symbol->update, $_match, $this->frame);

                if ($update !== null) {
                    if (!is_array($update)) {
                        throw new JException("T2011", $this->symbol->update->position, $update);
                    }

                    foreach ($update as $prop => $value) {
                        if (is_array($_match)) {
                            $_match[$prop] = $value;
                        }
                    }
                }

                if ($this->symbol->delete !== null) {
                    $deletions = $this->jsonata->evaluateAst($this->symbol->delete, $_match, $this->frame);
                    $val = $deletions;
                    if ($deletions !== null) {
                        if (!is_array($deletions)) {
                            $deletions = [$deletions];
                        }

                        if (!Utils::isArrayOfStrings($deletions)) {
                            throw new JException("T2012", $this->symbol->delete->position, $val);
                        }

                        foreach ($deletions as $deletion) {
                            if (is_array($_match)) {
                                unset($_match[$deletion]);
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
}
