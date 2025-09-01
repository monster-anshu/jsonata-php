<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class JsonataToken
{
    /**
     * @var string
     */
    public $type;
    /**
     * @var mixed
     */
    public $value;
    /**
     * @var int
     */
    public $position;
    /**
     * @param mixed $value
     */
    public function __construct(string $type, $value, int $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }
}
