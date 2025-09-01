<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _InfixAndPrefix extends _Infix
{
    /**
     * @var \Monster\JsonataPhp\_Prefix
     */
    public $prefix;

    public function __construct(Parser $parser, string $id, int $bp = 0)
    {
        parent::__construct($parser, $id, $bp);
        $this->prefix = new _Prefix($this->outerInstance, $id);
        $this->construct_args = func_get_args();
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
