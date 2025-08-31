<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class Symbol implements \JsonSerializable, \Stringable
{
    public ?string $type = null;
    public $value = null;
    public int $lbp = 0;
    public int $position = 0;

    public bool $keepArray = false;
    public bool $descending = false;
    public ?Symbol $expression = null;
    public ?array $seekingParent = null;   // List<Symbol>
    public ?array $errors = null;          // List<Exception>

    public ?array $steps = null;
    public ?Symbol $slot = null;
    public ?Symbol $nextFunction = null;
    public bool $keepSingletonArray = false;
    public bool $consarray = false;
    public int $level = 0;

    public $focus = null;
    public $token = null;
    public bool $thunk = false;

    // Procedure
    public ?Symbol $procedure = null;
    public ?array $arguments = null;
    public ?Symbol $body = null;
    public ?array $predicate = null;
    public ?array $stages = null;
    public $input = null;
    public $environment = null; // Frame
    public $tuple = null;
    public $expr = null;
    public ?Symbol $group = null;
    public $name = null;

    // Infix attributes
    public ?Symbol $lhs = null;
    public ?Symbol $rhs = null;
    public ?array $lhsObject = null;
    public ?array $rhsObject = null;
    public ?array $rhsTerms = null;
    public ?array $terms = null;

    // Ternary operator
    public ?Symbol $condition = null;
    public ?Symbol $then = null;
    public ?Symbol $_else = null;

    public ?array $expressions = null;

    // Error handling
    public $error = null;   // JException
    public $signature = null;

    // Prefix attributes
    public ?Symbol $pattern = null;
    public ?Symbol $update = null;
    public ?Symbol $delete = null;

    // Ancestor attributes
    public ?string $label = null;
    public $index = null;
    public bool $_jsonata_lambda = false;
    public ?Symbol $ancestor = null;

    protected $construct_args = [];
    public function __construct(public Parser $outerInstance, public ?string $id = null, public int $bp = 0)
    {
        $this->construct_args = func_get_args();
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
        $cl = new static(...$this->construct_args);
        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'construct_args') {
                continue;
            }
            // Deep clone for Symbol properties
            if ($value instanceof Symbol) {
                $cl->$key = $value->clone();
            } elseif (is_array($value)) {
                // Recursively clone arrays containing Symbol objects
                $cl->$key = array_map(fn ($item) => $item instanceof Symbol ? $item->clone() : $item, $value);
            } else {
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
        $vars = get_object_vars($this);
        $skip_keys = ['outerInstance' , '_jsonata_lambda', 'construct_args', 'id'];
        $result = [];
        foreach ($vars as $key => $value) {
            if (in_array($key, $skip_keys, true)) {
                continue;
            }
            if (!!$value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
