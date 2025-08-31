<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class JException extends \Exception {
    public $remaining;

    public function __construct(public $code, public $position = null, public $value = null, mixed $bb = null) {
        $valueStr = is_array($this->value) ? json_encode($this->value) : ($this->value ?: "");
        parent::__construct("Error {$this->code} at position {$this->position}: " . $valueStr);
    }
}