<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class JsonataToken
{
    public string $type;
    public string $value;
    public int $position;

    public function __construct(?string $type, string $value, int $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }
}