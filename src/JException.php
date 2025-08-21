<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class JException extends \Exception {
    public $remaining;

    public function __construct(public $code, public $position, public $value = null) {
        parent::__construct("Error {$this->code} at position {$this->position}: " . ($this->value ?: ""));
    }
}