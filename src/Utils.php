<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

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
     * Checks if an array is a "list" (keys are sequential integers from 0).
     *
     * @param mixed $arr The array to check.
     * @return bool True if the array is a list, false otherwise.
     */
  public static  function isArray(mixed $arr): bool
    {
        if (!is_array($arr)) {
            return false;
        }
        if (empty($arr)) {
            return true;
        }
        if($arr instanceof \ArrayObject) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }


    /**
     * Checks if an array is "associative" (keys are not sequential integers from 0).
     *
     * @param mixed $arr The array to check.
     * @return bool True if the array is associative, false otherwise.
     */
   public static function isAssoc(mixed $arr): bool
    {
        if(!is_array($arr)) {
            return false;
        }
        // A simple, fast check for a non-empty array
        if (empty($arr)) {
            return false;
        }

        // Returns true if any key is a string or the keys are not a range starting from 0
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Checks if a variable is an array of strings.
     *
     * @param mixed $v The variable to check.
     * @return bool
     */
    public static function isArrayOfStrings($v)
    {
        if (self::isArray($v)) {
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
        if (self::isArray($v)) {
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
        return $o instanceof _JFunctionCallable;
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
        $sequence = new JList();
        $sequence->sequence = true;

        if ($el !== self::$none) {
            // Check if the element is an array with only one item
            if (self::isArray($el) && count($el) === 1) {
                $sequence[] = reset($el);
            } else {
                $sequence[] = $el;
            }
        }

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
        if (self::isArray($val)) {
            self::convertArrayNulls($val);
        } elseif (self::isAssoc($val)) {
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

    public static function RangeList(mixed $a, mixed $b): array
    {
        return new RangeList($a, $b);
    }
}

// PHP doesn't support nested classes in the same way as Python.
// We'll define the nested classes outside of the main Utils class.
class JList extends \ArrayObject
{
    public bool $sequence = false;
    public bool $outerWrapper = false;
    public bool $tupleStream = false;
    public bool $keepSingleton = false;
    public bool $cons = false;

    public function __construct(array $input = [], int $flags = 0, string $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
    }

    /**
     * Check if list is empty
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Return size of list
     */
    public function size(): int
    {
        return $this->count();
    }

    /**
     * Get element by index
     */
    public function get(int $index): mixed
    {
        return $this->offsetExists($index) ? $this->offsetGet($index) : null;
    }

    /**
     * Convert to normal PHP array
     */
    public function toArray(): array
    {
        return $this->getArrayCopy();
    }
}


class RangeList extends JList
{
    private $size;

    public function __construct(private $a, private $b)
    {
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

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator(range($this->a, $this->b));
    }
}
