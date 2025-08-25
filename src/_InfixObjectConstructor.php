<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;


class _InfixObjectConstructor extends _Infix
{
    public function __construct(Parser $outerInstance, int $bp)
    {
        parent::__construct($outerInstance, "{", $bp);
        $this->construct_args = func_get_args();
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