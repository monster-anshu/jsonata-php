<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class Signature
{
    /**
     * @var string
     */
    public $functionName;
    public const SERIAL_VERSION_UID = -450755246855587271;

    /**
     * @var string
     */
    public $signature;

    /**
     * @var \Monster\JsonataPhp\SignatureParam
     */
    public $param;

    /** @var SignatureParam[] */
    public $params = [];

    /**
     * @var \Monster\JsonataPhp\SignatureParam
     */
    public $prevParam;

    /**
     * @var \RegexIterator|null
     */
    public $regex;

    /**
     * @var string
     */
    public $compiledSignature = "";

    public function __construct(string $signature, string $functionName)
    {
        $this->functionName = $functionName;
        $this->param = new SignatureParam();
        $this->prevParam = $this->param;
        $this->parseSignature($signature);
    }

    /**
     * @param string $functionName
     */
    public function setFunctionName($functionName): void
    {
        $this->functionName = $functionName;
    }

    /**
     * @param string $string
     * @param int $start
     * @param string $open
     * @param string $close
     */
    public function findClosingBracket($string, $start, $open, $close): int
    {
        $depth = 1;
        $position = $start;

        while ($position < strlen($string) - 1) {
            ++$position;
            $symbol = $string[$position];
            if ($symbol === $close) {
                --$depth;
                if ($depth === 0) {
                    break;
                }
            } elseif ($symbol === $open) {
                ++$depth;
            }
        }

        return $position;
    }

    /**
     * @param mixed $value
     */
    public function getSymbol($value): string
    {
        if ($value === null) {
            return "m";
        }

        if (is_callable($value)) {
            return "f";
        }

        if ($value instanceof \Closure) {
            return "f";
        }

        if (is_string($value)) {
            return "s";
        }

        if (is_bool($value)) {
            return "b";
        }

        if (is_int($value) || is_float($value)) {
            return "n";
        }

        if (Utils::isArray($value)) {
            return "a";
        }

        if (Utils::isAssoc($value)) {
            return "o";
        }

        return "m"; // missing
    }

    public function next(): void
    {
        $this->params[] = $this->param;
        $this->prevParam = $this->param;
        $this->param = new SignatureParam();
    }

    /**
     * @param string $signature
     */
    public function parseSignature($signature): ?\RegexIterator
    {
        $position = 1;
        while ($position < strlen($signature)) {
            $symbol = $signature[$position];

            switch ($symbol) {
                case ':':
                    break 2;
                case 's':
                case 'n':
                case 'b':
                case 'l':
                case 'o':
                    $this->param->regex = "[" . $symbol . "m]";
                    $this->param->type = $symbol;
                    $this->next();
                    break;
                case 'a':
                    $this->param->regex = "[asnblfom]";
                    $this->param->type = $symbol;
                    $this->param->array = true;
                    $this->next();
                    break;
                case 'f':
                    $this->param->regex = "f";
                    $this->param->type = $symbol;
                    $this->next();
                    break;
                case 'j':
                    $this->param->regex = "[asnblom]";
                    $this->param->type = $symbol;
                    $this->next();
                    break;
                case 'x':
                    $this->param->regex = "[asnblfom]";
                    $this->param->type = $symbol;
                    $this->next();
                    break;
                case '-':
                    $this->prevParam->context = true;
                    $this->prevParam->regex .= "?";
                    break;
                case '?':
                case '+':
                    $this->prevParam->regex .= $symbol;
                    break;
                case '(':
                    $end = $this->findClosingBracket($signature, $position, '(', ')');
                    $choice = substr($signature, $position + 1, $end - $position - 1);
                    if (strpos($choice, "<") === false) {
                        $this->param->regex = "[" . $choice . "m]";
                    } else {
                        throw new \RuntimeException("Choice groups with parameterized types not supported");
                    }

                    $this->param->type = "(" . $choice . ")";
                    $position = $end;
                    $this->next();
                    break;
                case '<':
                    $test = $this->prevParam->type;
                    if ($test !== null) {
                        if ($test === "a" || $test === "f") {
                            $end = $this->findClosingBracket($signature, $position, '<', '>');
                            $this->prevParam->subtype = substr($signature, $position + 1, $end - $position - 1);
                            $position = $end;
                        } else {
                            throw new \RuntimeException("Type params only valid for functions and arrays");
                        }
                    } else {
                        throw new \RuntimeException("Type params only valid for functions and arrays");
                    }

                    break;
            }

            ++$position;
        }

        $regexStr = "^";
        foreach ($this->params as $param) {
            $regexStr .= "(" . $param->regex . ")";
        }

        $regexStr .= "$";

        $this->compiledSignature = $regexStr;
        $this->regex = new \RegexIterator(
            new \ArrayIterator([]),
            '/' . $regexStr . '/'
        );
        return $this->regex;
    }

    public function getNumberOfArgs(): int
    {
        return count($this->params);
    }

    public function getMinNumberOfArgs(): int
    {
        $res = 0;
        foreach ($this->params as $param) {
            if (strpos((string) $param->regex, "?") === false) {
                ++$res;
            }
        }

        return $res;
    }

    public function validate($args, $context): bool
    {
        //TODO: implement this
        return true;
    }
}

class SignatureParam
{
    /**
     * @var string|null
     */
    public $type;
    /**
     * @var string|null
     */
    public $regex;
    /**
     * @var bool
     */
    public $context = false;
    /**
     * @var bool
     */
    public $array = false;
    /**
     * @var string|null
     */
    public $subtype;
    /**
     * @var string|null
     */
    public $contextRegex;
    public function __toString(): string
    {
        return "Param " . ($this->type ?? "null") . " regex=" . ($this->regex ?? "null")
            . " ctx=" . ($this->context ? "true" : "false")
            . " array=" . ($this->array ? "true" : "false");
    }
}
