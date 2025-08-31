<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

/**
 * JFunction callable Lambda interface
 */
interface _JFunctionCallable
{
    public function call(mixed $input, array $args): mixed;
}
