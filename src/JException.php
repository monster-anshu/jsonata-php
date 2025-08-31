<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class JException extends \Exception implements \Stringable
{
    public $remaining;

    public function __construct(public $code, public $position = null, public $value = null, mixed $bb = null)
    {
        $valueStr = (Utils::isArray($this->value) || Utils::isAssoc($this->value) || is_object($this->value)) ? json_encode($this->value) : ($this->value ?: "");
        parent::__construct("Error {$this->code} at position {$this->position}: " . $valueStr);
    }

    public function __toString(): string
    {
        return "Error {$this->code} at position {$this->position}: " . $this->value;
    }

}
