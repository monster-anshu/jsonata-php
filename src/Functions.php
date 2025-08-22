<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Functions
{
    /**
     * Magic static caller – catches all undefined static method calls.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        // For now, just return something placeholder-ish
        return match ($name) {
            // If value exists, cast to string
            'string' => isset($arguments[0]) ? strval($arguments[0]) : "",
            'toBoolean' => isset($arguments[0]) ? (bool) $arguments[0] : false,
            // Stub fallback for unimplemented functions
            default => null,
        };
    }
}
