<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _PrefixDescendantWildcard extends _Prefix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "**");
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        $this->type = "descendant";
        return $this;
    }
}