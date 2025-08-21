<?php

declare(strict_types=1);

class JFunctionCallable
{

}

class Utils
{
    /**
     * @var object A singleton object representing a null value.
     */
    public static $nullValue;

    public static function init()
    {
        self::$nullValue = new class {
            public function __toString()
            {
                return 'null';
            }
        };
    }

    /**
     * Checks if a variable is a numeric type.
     *
     * @param mixed $v The variable to check.
     * @return bool
     * @throws JException If the number is not finite.
     */
    public static function isNumeric($v)
    {
        if (is_bool($v)) {
            return false;
        }
        if (is_int($v)) {
            return true;
        }
        if (is_float($v)) {
            if (is_nan($v)) {
                return false;
            }
            if (!is_finite($v)) {
                throw new JException("D1001", 0, $v);
            }
            return true;
        }
        return false;
    }

    /**
     * Checks if a variable is an array of strings.
     *
     * @param mixed $v The variable to check.
     * @return bool
     */
    public static function isArrayOfStrings($v)
    {
        if (is_array($v)) {
            foreach ($v as $o) {
                if (!is_string($o)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Checks if a variable is an array of numbers.
     *
     * @param mixed $v The variable to check.
     * @return bool
     */
    public static function isArrayOfNumbers($v)
    {
        if (is_array($v)) {
            foreach ($v as $o) {
                if (!self::isNumeric($o)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Checks if a variable is a function.
     *
     * @param mixed $o The variable to check.
     * @return bool
     */
    public static function isFunction($o)
    {
        // Placeholder for the JFunctionCallable class from the Python code.
        // In a real implementation, you would need to define this class.
        return $o instanceof JFunctionCallable;
    }

    /**
     * @var object A singleton object for creating sequences.
     */
    public static $none;

    /**
     * Creates a new sequence with an optional element.
     *
     * @param mixed $el
     * @return JList
     */
    public static function createSequence($el = null)
    {
        if (self::$none === null) {
            self::$none = new \stdClass();
        }

        if ($el !== self::$none) {
            if (is_array($el) && count($el) === 1) {
                $sequence = new JList($el);
            } else {
                $sequence = new JList([$el]);
            }
        } else {
            $sequence = new JList();
        }
        $sequence->sequence = true;
        return $sequence;
    }

    /**
     * Creates a sequence from an iterable.
     *
     * @param iterable $it
     * @return JList
     */
    public static function createSequenceFromIter(iterable $it)
    {
        $sequence = new JList($it);
        $sequence->sequence = true;
        return $sequence;
    }

    /**
     * Checks if a result is a sequence.
     *
     * @param mixed $result
     * @return bool
     */
    public static function isSequence($result)
    {
        return $result instanceof JList && $result->sequence;
    }

    /**
     * Converts a number to the most appropriate integer or float.
     *
     * @param mixed $n The number to convert.
     * @return int|float|null
     */
    public static function convertNumber($n)
    {
        if (!self::isNumeric($n)) {
            return null;
        }

        if (is_int($n) || (is_float($n) && (int) $n == $n)) {
            return (int) $n;
        }

        return (float) $n;
    }

    /**
     * Converts a JSONata null value placeholder to a PHP null.
     *
     * @param mixed $val The value to convert.
     * @return mixed
     */
    public static function convertValue($val)
    {
        if (self::$nullValue === null) {
            self::init();
        }
        return $val !== self::$nullValue ? $val : null;
    }

    /**
     * Recursively converts null placeholders in an array.
     *
     * @param array $res The array to process.
     */
    public static function convertArrayNulls(&$res)
    {
        foreach ($res as $key => $val) {
            $v = self::convertValue($val);
            if ($v !== $val) {
                $res[$key] = $v;
            }
            self::recurse($val);
        }
    }

    /**
     * Recursively converts null placeholders in an object.
     *
     * @param object $res The object to process.
     */
    public static function convertObjectNulls(&$res)
    {
        foreach ($res as $key => $val) {
            $v = self::convertValue($val);
            if ($v !== $val) {
                $res->$key = $v;
            }
            self::recurse($val);
        }
    }

    /**
     * Recursively traverses a value to convert null placeholders.
     *
     * @param mixed $val The value to traverse.
     */
    public static function recurse(&$val)
    {
        if (is_array($val)) {
            self::convertArrayNulls($val);
        } elseif (is_object($val)) {
            self::convertObjectNulls($val);
        }
    }

    /**
     * Converts all JSONata null placeholders to PHP nulls in a result.
     *
     * @param mixed $res The result to process.
     * @return mixed
     */
    public static function convertNulls($res)
    {
        self::recurse($res);
        return self::convertValue($res);
    }
}

// PHP doesn't support nested classes in the same way as Python.
// We'll define the nested classes outside of the main Utils class.

class JList extends \ArrayObject
{
    public $sequence = false;
    public $outerWrapper = false;
    public $tupleStream = false;
    public $keepSingleton = false;
    public $cons = false;

    public function __construct($input = [], $flags = 0, $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
    }
}

class RangeList extends JList
{
    private $a;
    private $b;
    private $size;

    public function __construct($left, $right)
    {
        $this->a = $left;
        $this->b = $right;
        $this->size = $this->b - $this->a + 1;
        parent::__construct();
    }

    public function count(): int
    {
        return $this->size;
    }

    public function offsetGet($index): mixed
    {
        if ($index < $this->size) {
            return Utils::convertNumber($this->a + $index);
        }
        throw new \OutOfBoundsException("Index out of bounds: " . $index);
    }

    public function getIterator(): Iterator
    {
        return new \ArrayIterator(range($this->a, $this->b));
    }
}
