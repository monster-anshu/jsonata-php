<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

/**
 * JFunction callable Lambda interface
 */
interface _JFunctionCallable
{
    /**
     * @param mixed $input
     * @param mixed[] $args
     * @return mixed
     */
    public function call($input, $args);
}
