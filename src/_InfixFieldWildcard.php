<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixFieldWildcard extends _Infix
{
    public function __construct(Parser $parser)
    {
        parent::__construct($parser, "*");
    }

    public function nud(): Symbol
    {
        $this->type = "wildcard";
        return $this;
    }
}
