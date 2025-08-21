<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class JException extends \Exception {
    public $code;
    public $position;
    public $value;
    public $remaining;

    public function __construct($code, $position, $value = null) {
        $this->code = $code;
        $this->position = $position;
        $this->value = $value;
        parent::__construct("Error $code at position $position: " . ($value ?: ""));
    }
}