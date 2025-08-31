<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class JsonataToken
{
    public function __construct(public string $type, public mixed $value, public int $position)
    {
    }
}
