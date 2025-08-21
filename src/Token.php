<?php

declare(strict_types=1);

class JSONATA_TOKEN
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