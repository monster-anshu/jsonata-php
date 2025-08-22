<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Symbol implements \JsonSerializable, \Stringable
{
    public ?string $type = null;
    public $value;
    public int $lbp;
    public int $position;
    public bool $keepArray;
    public bool $descending;
    public ?Symbol $expression = null;
    public ?array $seekingParent = null;
    public ?array $errors = null;
    public ?array $steps = null;
    public ?Symbol $slot = null;
    public ?Symbol $nextFunction = null;
    public bool $keepSingletonArray;
    public bool $consarray;
    public int $level;
    public $focus;
    public $token;
    public bool $thunk;

    // Procedure attributes
    public ?Symbol $procedure = null;
    public ?array $arguments = null;
    public ?Symbol $body = null;
    public ?array $predicate = null;
    public ?array $stages = null;
    public $input;
    // public ?Parser\Jsonata\Frame $environment; // Removed as it creates a circular reference in the Python code
    public $tuple;
    public $expr;
    public ?Symbol $group = null;
    public ?Symbol $name = null;

    // Infix attributes
    public ?Symbol $lhs = null;
    public ?Symbol $rhs = null;
    public ?array $lhsObject = null;
    public ?array $rhsObject = null;
    public ?array $rhsTerms = null;
    public ?array $terms = null;

    // Ternary operator attributes
    public ?Symbol $condition = null;
    public ?Symbol $then = null;
    public ?Symbol $else = null; // _else is a reserved keyword in PHP, so we change it to 'else'

    public ?array $expressions = null;

    // processAST error handling
    public ?JException $error = null;
    public $signature;

    // Prefix attributes
    public ?Symbol $pattern = null;
    public ?Symbol $update = null;
    public ?Symbol $delete = null;

    // Ancestor attributes
    public ?string $label = null;
    public $index;
    public bool $jsonataLambda;
    public ?Symbol $ancestor = null;

    public bool $_jsonata_lambda = false;
    public ?_Frame $environment = null;

    public function __construct(public Parser $outerInstance, public ?string $id = null, public int $bp = 0)
    {
        $this->value = $this->id;
    }

    // `nud` method (Null Denotation)
    public function nud(): Symbol
    {
        // error - symbol has been invoked as a unary operator
        $err = new JException("S0211", $this->position, $this->value);
        if ($this->outerInstance->recover) {
            return new Symbol($this->outerInstance, "(error)");
        } else {
            throw $err;
        }
    }

    // `led` method (Left Denotation)
    public function led(Symbol $left): Symbol
    {
        throw new \Exception("led not implemented"); // Using a generic exception since `NotImplementedError` is Python-specific.
    }

    public function create(): Symbol
    {
        // This method creates a shallow copy, similar to Python's copy.copy()
        $cl = $this->clone();
        return $cl;
    }

    public function clone(): Symbol
    {
        // `static` ensures the correct class (late static binding).
        $cl = new static($this->outerInstance, null);

        foreach ($this as $key => $value) {
            if ($key !== 'outerInstance') {
                $cl->$key = $value;
            }
        }

        return $cl;
    }

    // The __toString magic method provides a string representation of the object,
    // similar to Python's __repr__.
    public function __toString(): string
    {
        // get_class($this) returns the class name of the current object.
        $className = static::class;
        return $className . " " . $this->id . " value=" . $this->value;
    }

    public function jsonSerialize(): array
    {
        // get all public properties as an array
        $vars = get_object_vars($this);
        unset($vars['outerInstance'], $vars['error']);
        // $vars = [
        //     'steps' => $this->steps,
        //     'id' => $this->id,
        //     'type' => $this->type,
        //     'value' => $this->value,
        // ];

        return $vars;
    }
}
