<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _PrefixObjectTransformer extends _Prefix
{
    public function __construct(Parser $outerInstance)
    {
        parent::__construct($outerInstance, "|");
        $this->construct_args = func_get_args();
    }

    public function nud(): Symbol
    {
        $this->type = "transform";
        $this->pattern = $this->outerInstance->expression(0);
        $this->outerInstance->advance("|");
        $this->update = $this->outerInstance->expression(0);
        if ($this->outerInstance->node->id === ",") {
            $this->outerInstance->advance(",");
            $this->delete = $this->outerInstance->expression(0);
        }
        $this->outerInstance->advance("|");
        return $this;
    }
}