<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _JFunctionSignatureValidation
{
    /**
     * @param mixed $args
     * @param mixed $context
     * @return mixed
     */
    public function validate($args, $context);
}
