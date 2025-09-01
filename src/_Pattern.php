<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _Pattern
{
    /**
     * @readonly
     * @var string
     */
    private $regex;
    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }

    /**
     * @param string $subject
     */
    public function matches($subject): bool
    {
        return (bool) preg_match($this->regex, $subject);
    }

    /**
     * @param string $subject
     * @param string $replacement
     */
    public function replace($subject, $replacement): string
    {
        return preg_replace($this->regex, $replacement, $subject);
    }
}
