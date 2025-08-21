<?php

declare(strict_types=1);

class Symbol implements JsonSerializable
{
    // In PHP, class properties are declared with a visibility modifier.
    // We use public for simplicity, but you can change this to public
    // or protected as needed. The ? before the type denotes a nullable property.
    public ?string $id;
    public ?string $type;
    public $value; // PHP's `mixed` type can be used for Any, but for older PHP versions, no type hint is the equivalent.
    public int $bp;
    public int $lbp;
    public int $position;
    public bool $keepArray;
    public bool $descending;
    public ?Symbol $expression;
    public ?array $seekingParent;
    public ?array $errors;
    public ?array $steps;
    public ?Symbol $slot;
    public ?Symbol $nextFunction;
    public bool $keepSingletonArray;
    public bool $consarray;
    public int $level;
    public $focus;
    public $token;
    public bool $thunk;

    // Procedure attributes
    public ?Symbol $procedure;
    public ?array $arguments;
    public ?Symbol $body;
    public ?array $predicate;
    public ?array $stages;
    public $input;
    // public ?Parser\Jsonata\Frame $environment; // Removed as it creates a circular reference in the Python code
    public $tuple;
    public $expr;
    public ?Symbol $group;
    public ?Symbol $name;

    // Infix attributes
    public ?Symbol $lhs;
    public ?Symbol $rhs;
    public ?array $lhsObject;
    public ?array $rhsObject;
    public ?array $rhsTerms;
    public ?array $terms;

    // Ternary operator attributes
    public ?Symbol $condition;
    public ?Symbol $then;
    public ?Symbol $else; // _else is a reserved keyword in PHP, so we change it to 'else'

    public ?array $expressions;

    // processAST error handling
    public ?JException $error;
    public $signature;

    // Prefix attributes
    public ?Symbol $pattern;
    public ?Symbol $update;
    public ?Symbol $delete;

    // Ancestor attributes
    public ?string $label;
    public $index;
    public bool $jsonataLambda;
    public ?Symbol $ancestor;

    // The PHP equivalent of Python's _outer_instance
    public Parser $outerInstance;

    public function __construct(Parser $outerInstance, ?string $id = null, int $bp = 0)
    {
        $this->outerInstance = $outerInstance;
        $this->id = $id;
        $this->value = $id;
        $this->bp = $bp;
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
        $className = get_class($this);
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
