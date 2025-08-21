<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;


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