<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class Symbol implements \JsonSerializable
{
    /**
     * @var \Monster\JsonataPhp\Parser
     */
    public $outerInstance;
    /**
     * @var string|null
     */
    public $id;
    /**
     * @var int
     */
    public $bp = 0;
    /**
     * @var string|null
     */
    public $type;

    public $value;

    /**
     * @var int
     */
    public $lbp = 0;

    /**
     * @var int
     */
    public $position = 0;

    /**
     * @var bool
     */
    public $keepArray = false;

    /**
     * @var bool
     */
    public $descending = false;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $expression;

    /**
     * @var mixed[]|null
     */
    public $seekingParent;
       // List<Symbol>
    /**
     * @var mixed[]|null
     */
    public $errors;          // List<Exception>
    /**
     * @var mixed[]|null
     */
    public $steps;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $slot;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $nextFunction;

    /**
     * @var bool
     */
    public $keepSingletonArray = false;

    /**
     * @var bool
     */
    public $consarray = false;

    /**
     * @var int
     */
    public $level = 0;

    public $focus;

    public $token;

    /**
     * @var bool
     */
    public $thunk = false;

    // Procedure
    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $procedure;

    /**
     * @var mixed[]|null
     */
    public $arguments;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $body;

    /**
     * @var mixed[]|null
     */
    public $predicate;

    /**
     * @var mixed[]|null
     */
    public $stages;

    public $input;

    public $environment;
     // Frame
    public $tuple;

    public $expr;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $group;

    public $name;

    // Infix attributes
    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $lhs;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $rhs;

    /**
     * @var mixed[]|null
     */
    public $lhsObject;

    /**
     * @var mixed[]|null
     */
    public $rhsObject;

    /**
     * @var mixed[]|null
     */
    public $rhsTerms;

    /**
     * @var mixed[]|null
     */
    public $terms;

    // Ternary operator
    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $condition;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $then;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $_else;

    /**
     * @var mixed[]|null
     */
    public $expressions;

    // Error handling
    public $error;
       // JException
    public $signature;

    // Prefix attributes
    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $pattern;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $update;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $delete;

    // Ancestor attributes
    /**
     * @var string|null
     */
    public $label;

    public $index;

    /**
     * @var bool
     */
    public $_jsonata_lambda = false;

    /**
     * @var \Monster\JsonataPhp\Symbol|null
     */
    public $ancestor;

    protected $construct_args = [];

    public function __construct(Parser $outerInstance, ?string $id = null, int $bp = 0)
    {
        $this->outerInstance = $outerInstance;
        $this->id = $id;
        $this->bp = $bp;
        $this->construct_args = func_get_args();
        $this->value = $this->id;
    }

    // `nud` method (Null Denotation)
    public function nud(): Symbol
    {
        // error - symbol has been invoked as a unary operator
        $jException = new JException("S0211", $this->position, $this->value);
        if ($this->outerInstance->recover) {
            return new Symbol($this->outerInstance, "(error)");
        } else {
            throw $jException;
        }
    }

    // `led` method (Left Denotation)
    /**
     * @param \Monster\JsonataPhp\Symbol $symbol
     */
    public function led($symbol): Symbol
    {
        throw new \Exception("led not implemented"); // Using a generic exception since `NotImplementedError` is Python-specific.
    }

    public function create(): Symbol
    {
        // This method creates a shallow copy, similar to Python's copy.copy()
        $symbol = $this->clone();
        return $symbol;
    }

    public function clone(): Symbol
    {
        // `static` ensures the correct class (late static binding).
        $static = new static(...$this->construct_args);
        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'construct_args') {
                continue;
            }

            // Deep clone for Symbol properties
            if ($value instanceof Symbol) {
                $static->$key = $value->clone();
            } elseif (is_array($value)) {
                // Recursively clone arrays containing Symbol objects
                $static->$key = array_map(function ($item) {
                    return $item instanceof Symbol ? $item->clone() : $item;
                }, $value);
            } else {
                $static->$key = $value;
            }
        }

        return $static;
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

            if ((bool) $value) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
