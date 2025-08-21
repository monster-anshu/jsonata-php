<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Signature
{
    const SERIAL_VERSION_UID = -450755246855587271;

    public string $signature;
    public string $functionName;

    public SignatureParam $param;
    /** @var SignatureParam[] */
    public array $params = [];
    public SignatureParam $prevParam;
    public ?\RegexIterator $regex = null;
    public string $compiledSignature = "";

    public function __construct(string $signature, string $function)
    {
        $this->param = new SignatureParam();
        $this->prevParam = $this->param;
        $this->functionName = $function;
        $this->parseSignature($signature);
    }

    public function setFunctionName(string $functionName): void
    {
        $this->functionName = $functionName;
    }

    public function findClosingBracket(string $string, int $start, string $open, string $close): int
    {
        $depth = 1;
        $position = $start;

        while ($position < strlen($string) - 1) {
            $position++;
            $symbol = $string[$position];
            if ($symbol === $close) {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($symbol === $open) {
                $depth++;
            }
        }
        return $position;
    }

    public function getSymbol(mixed $value): string
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
        if (is_array($value)) {
            return "a";
        }
        if (is_object($value)) {
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

    public function parseSignature(string $signature): ?\RegexIterator
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
            $position++;
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
        foreach ($this->params as $p) {
            if (strpos($p->regex, "?") === false) {
                $res++;
            }
        }
        return $res;
    }
}

class SignatureParam
{
    public ?string $type = null;
    public ?string $regex = null;
    public bool $context = false;
    public bool $array = false;
    public ?string $subtype = null;
    public ?string $contextRegex = null;

    public function __toString(): string
    {
        return "Param " . ($this->type ?? "null") . " regex=" . ($this->regex ?? "null")
            . " ctx=" . ($this->context ? "true" : "false")
            . " array=" . ($this->array ? "true" : "false");
    }
}
