<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _JFunction implements _JFunctionCallable, _JFunctionSignatureValidation
{
    public ?string $functionName = null;
    public ?Signature $signature = null;

    private ?\ReflectionMethod $method = null;
    private mixed $methodInstance = null;

    public function __construct(private readonly _JFunctionCallable|string $function, ?string $signature = null, ?string $className = null, ?string $methodName = null)
    {
        if ($signature !== null) {
            // use class name as default, gets overwritten once the function is registered
            $this->signature = new Signature($signature, $className);
        }
    }

    public static function fromMethod(
        string $functionName,
        string $signature,
        string $className,
        mixed $instance,
        string $implMethodName
    ): self {
        $obj = new self(new class () implements _JFunctionCallable {
            public function call(mixed $input, array $args): mixed
            {
                return null; // placeholder, gets replaced by reflection call
            }
        });

        $obj->functionName = $functionName;
        $obj->signature = new Signature($signature, $functionName);
        $obj->methodInstance = $instance;

        try {
            $obj->method = new \ReflectionMethod($className, $implMethodName);
        } catch (\ReflectionException) {
            error_log("Function not implemented: $functionName impl=$implMethodName");
        }

        return $obj;
    }

    public function call(mixed $input, array $args): mixed
    {
        try {
            if ($this->function !== null) {
                return $this->function->call($input, $args);
            } elseif ($this->method !== null) {
                return $this->method->invokeArgs($this->methodInstance, $args);
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        return null;
    }

    public function validate(mixed $args, mixed $context): mixed
    {
        if ($this->signature !== null) {
            return $this->signature->validate($args, $context);
        }
        return $args;
    }

    public function getNumberOfArgs(): int
    {
        return $this->method !== null ? $this->method->getNumberOfParameters() : 0;
    }
}
