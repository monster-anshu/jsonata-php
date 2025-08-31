<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _PrefixDescendantWildcard extends _Prefix
{
    public function __construct(Parser $parser)
    {
        parent::__construct($parser, "**");
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        $this->type = "descendant";
        return $this;
    }
}
