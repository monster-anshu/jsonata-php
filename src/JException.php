<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class JException extends \Exception implements \Stringable
{
    public $remaining;

    public function __construct(public $code, public $position = null, public $value = null)
    {
        $valueStr = (Utils::isArray($this->value) || Utils::isAssoc($this->value) || is_object($this->value)) ? json_encode($this->value) : ($this->value ?: "");
        parent::__construct(sprintf('Error %s at position %s: ', $this->code, $this->position) . $valueStr);
    }

    public function __toString(): string
    {
        return sprintf('Error %s at position %s: ', $this->code, $this->position) . $this->value;
    }

}
