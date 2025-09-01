<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class _JFunction implements _JFunctionCallable, _JFunctionSignatureValidation
{
    /**
     * @readonly
     * @var \Monster\JsonataPhp\_JFunctionCallable|string
     */
    private $function;
    /**
     * @var string|null
     */
    public $functionName;

    /**
     * @var \Monster\JsonataPhp\Signature|null
     */
    public $signature;

    /**
     * @var \ReflectionMethod|null
     */
    private $reflectionMethod;

    /**
     * @var mixed
     */
    private $methodInstance = null;

    /**
     * @param \Monster\JsonataPhp\_JFunctionCallable|string $function
     */
    public function __construct($function, ?string $signature = null, ?string $className = null)
    {
        $this->function = $function;
        if ($signature !== null) {
            // use class name as default, gets overwritten once the function is registered
            $this->signature = new Signature($signature, $className);
        }
    }

    /**
     * @param string $functionName
     * @param string $signature
     * @param string $className
     * @param mixed $instance
     * @param string $implMethodName
     */
    public static function fromMethod(
        $functionName,
        $signature,
        $className,
        $instance,
        $implMethodName
    ): self {
        $obj = new self(new class () implements _JFunctionCallable {
            /**
             * @param mixed $input
             * @param mixed[] $args
             * @return mixed
             */
            public function call($input, $args)
            {
                return null; // placeholder, gets replaced by reflection call
            }
        });

        $obj->functionName = $functionName;
        $obj->signature = new Signature($signature, $functionName);
        $obj->methodInstance = $instance;

        try {
            $obj->reflectionMethod = new \ReflectionMethod($className, $implMethodName);
            $obj->reflectionMethod->setAccessible(true);
        } catch (\ReflectionException $exception) {
            error_log(sprintf('Function not implemented: %s impl=%s', $functionName, $implMethodName));
        }

        return $obj;
    }

    /**
     * @param mixed $input
     * @param mixed[] $args
     * @return mixed
     */
    public function call($input, $args)
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

    /**
     * @param mixed $args
     * @param mixed $context
     * @return mixed
     */
    public function validate($args, $context)
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
