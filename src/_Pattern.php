<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class _Pattern
{
    public function __construct(private readonly string $regex)
    {
    }

    public function matches(string $subject): bool
    {
        return (bool) preg_match($this->regex, $subject);
    }

    public function replace(string $subject, string $replacement): string
    {
        return preg_replace($this->regex, $replacement, $subject);
    }
}
