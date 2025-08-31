<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _JFunction implements _JFunctionCallable, _JFunctionSignatureValidation
{
    public ?string $functionName = null;

    public ?Signature $signature = null;

    private ?\ReflectionMethod $reflectionMethod = null;

    private mixed $methodInstance = null;

    public function __construct(private readonly _JFunctionCallable|string $function, ?string $signature = null, ?string $className = null)
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
            $obj->reflectionMethod = new \ReflectionMethod($className, $implMethodName);
        } catch (\ReflectionException) {
            error_log(sprintf('Function not implemented: %s impl=%s', $functionName, $implMethodName));
        }

        return $obj;
    }

    public function call(mixed $input, array $args): mixed
    {
        try {
            if ($this->function !== null) {
                return $this->function->call($input, $args);
            } elseif ($this->reflectionMethod !== null) {
                return $this->reflectionMethod->invokeArgs($this->methodInstance, $args);
            }
        } catch (\Throwable $throwable) {
            if ($throwable instanceof \RuntimeException) {
                throw $throwable;
            }

            throw new \RuntimeException($throwable->getMessage(), 0, $throwable);
        }

        return null;
    }

    public function validate(mixed $args, mixed $context): mixed
    {
        if ($this->signature instanceof \Monster\JsonataPhp\Signature) {
            return $this->signature->validate($args, $context);
        }

        return $args;
    }

    public function getNumberOfArgs(): int
    {
        return $this->reflectionMethod !== null ? $this->reflectionMethod->getNumberOfParameters() : 0;
    }
}
