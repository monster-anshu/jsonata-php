<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixObjectConstructor extends _Infix
{
    public function __construct(Parser $parser, int $bp)
    {
        parent::__construct($parser, "{", $bp);
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        return $this->outerInstance->objectParser(null);
    }

    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     */
    public function led($symbol): Symbol
    {
        return $this->outerInstance->objectParser($symbol);
    }
}
