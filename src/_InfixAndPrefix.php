<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _InfixAndPrefix extends _Infix
{
    public _Prefix $prefix;

    public function __construct(Parser $outerInstance, string $id, int $bp = 0)
    {
        parent::__construct($outerInstance, $id, $bp);
        $this->prefix = new _Prefix($this->outerInstance, $id);
    }

    public function nud(): Symbol
    {
        return $this->prefix->nud();
    }

    public function __clone()
    {
        // Make sure to allocate a new Prefix when cloning
        $this->prefix = new _Prefix($this->outerInstance, $this->id);
    }
}
