<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Prefix extends Symbol
{

    public function nud(): Symbol
    {
        $this->expression = $this->outerInstance->expression(70);
        $this->type = "unary";
        return $this;
    }
}
