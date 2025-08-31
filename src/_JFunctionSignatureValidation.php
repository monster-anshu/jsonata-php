<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

interface _JFunctionSignatureValidation
{
    public function validate(mixed $args, mixed $context): mixed;
}
