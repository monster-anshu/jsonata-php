<?php

declare(strict_types=1);
namespace Monster\JsonataPhp;

class Tokenizer
{
    private int $position = 0;
    private readonly int $length;

    public const operators = [
        "." => 75,
        "[" => 80,
        "]" => 0,
        "{" => 70,
        "}" => 0,
        "(" => 80,
        ")" => 0,
        "," => 0,
        "@" => 80,
        "#" => 80,
        ";" => 80,
        ":" => 80,
        "?" => 20,
        "+" => 50,
        "-" => 50,
        "*" => 60,
        "/" => 60,
        "%" => 60,
        "|" => 20,
        "=" => 40,
        "<" => 40,
        ">" => 40,
        "^" => 40,
        "**" => 60,
        ".." => 20,
        ":=" => 10,
        "!=" => 40,
        "<=" => 40,
        ">=" => 40,
        "~>" => 40,
        "?:" => 40,
        "??" => 40,
        "and" => 30,
        "or" => 25,
        "in" => 40,
        "&" => 50,
        "!" => 0,
        "~" => 0
    ];

    private const escapes = [
        "\"" => "\"",
        "\\" => "\\",
        "/" => "/",
        "b" => "\b",
        "f" => "\f",
        "n" => "\n",
        "r" => "\r",
        "t" => "\t"
    ];

    public function __construct(private string $path)
    {
        $this->length = strlen($this->path);
    }

    private function create(string $type, mixed $value): JsonataToken
    {
        return new JsonataToken($type, $value, $this->position);
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length && preg_match('/\s/', $this->path[$this->position])) {
            $this->position++;
        }
    }

    private function scanRegex(): JsonataToken
    {
        $start = $this->position;
        $pattern = "";
        $depth = 0;

        while ($this->position < $this->length) {
            $ch = $this->path[$this->position];
            if ($ch === '/' && $depth === 0 && ($this->position === 0 || $this->path[$this->position - 1] !== '\\')) {
                $pattern = substr($this->path, $start, $this->position - $start);
                $this->position++;
                // flags
                $flags = "";
                while ($this->position < $this->length && str_contains("im", $this->path[$this->position])) {
                    $flags .= $this->path[$this->position];
                    $this->position++;
                }
                return $this->create("regex", ["pattern" => $pattern, "flags" => $flags . "g"]);
            }
            if (in_array($ch, ['(', '[', '{']) && ($this->position === 0 || $this->path[$this->position - 1] !== '\\')) {
                $depth++;
            }
            if (in_array($ch, [')', ']', '}']) && ($this->position === 0 || $this->path[$this->position - 1] !== '\\')) {
                $depth--;
            }
            $this->position++;
        }
        throw new \Exception("Unterminated regex at pos {$this->position}");
    }

    private function readString(string $quoteType): JsonataToken
    {
        $this->position++;
        $qstr = "";
        while ($this->position < $this->length) {
            $ch = $this->path[$this->position];
            if ($ch === "\\") {
                $this->position++;
                if ($this->position >= $this->length) {
                    throw new \Exception("Unterminated escape sequence");
                }
                $esc = $this->path[$this->position];
                if (self::escapes[$esc] ?? null) {
                    $qstr .= self::escapes[$esc];
                } elseif ($esc === "u") {
                    $octets = substr($this->path, $this->position + 1, 4);
                    if (preg_match('/^[0-9a-fA-F]{4}$/', $octets)) {
                        $qstr .= mb_convert_encoding(pack("H*", $octets), "UTF-8", "UTF-16BE");
                        $this->position += 4;
                    } else {
                        throw new \Exception("Invalid unicode escape");
                    }
                } else {
                    throw new \Exception("Illegal escape sequence: \\$esc");
                }
            } elseif ($ch === $quoteType) {
                $this->position++;
                return $this->create("string", $qstr);
            } else {
                $qstr .= $ch;
            }
            $this->position++;
        }
        throw new \Exception("Unterminated string");
    }

    public function next(bool $prefix = true): ?JsonataToken
    {
        if ($this->position >= $this->length)
            return null;

        $this->skipWhitespace();
        if ($this->position >= $this->length)
            return null;

        $ch = $this->path[$this->position];

        // comments
        if ($ch === '/' && $this->position + 1 < $this->length && $this->path[$this->position + 1] === '*') {
            $this->position += 2;
            while ($this->position < $this->length - 1) {
                if ($this->path[$this->position] === '*' && $this->path[$this->position + 1] === '/') {
                    $this->position += 2;
                    return $this->next($prefix);
                }
                $this->position++;
            }
            throw new \Exception("Unterminated comment");
        }

        // regex
        if (!$prefix && $ch === '/') {
            $this->position++;
            return $this->scanRegex();
        }

        // double char operators
        foreach (["..", ":=", "!=", ">=", "<=", "**", "~>", "?:", "??"] as $op) {
            if (substr($this->path, $this->position, strlen($op)) === $op) {
                $this->position += strlen($op);
                return $this->create("operator", $op);
            }
        }

        // single char operator
        if (self::operators[$ch] ?? null) {
            $this->position++;
            return $this->create("operator", $ch);
        }

        // string
        if ($ch === '"' || $ch === "'") {
            return $this->readString($ch);
        }

        // number
        if (preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]+)?([Ee][-+]?[0-9]+)?/', substr($this->path, $this->position), $m)) {
            $this->position += strlen($m[0]);
            return $this->create("number", $m[0]);
        }

        // quoted names
        if ($ch === '`') {
            $this->position++;
            $end = strpos($this->path, '`', $this->position);
            if ($end !== false) {
                $name = substr($this->path, $this->position, $end - $this->position);
                $this->position = $end + 1;
                return $this->create("name", $name);
            }
            throw new \Exception("Unterminated backtick name");
        }

        // names and variables
        $i = $this->position;
        while ($i < $this->length && preg_match('/[A-Za-z0-9_\$]/', $this->path[$i])) {
            $i++;
        }
        $name = substr($this->path, $this->position, $i - $this->position);
        $this->position = $i;

        if ($name === "") {
            return null;
        }

        if ($name[0] === '$') {
            return $this->create("variable", substr($name, 1));
        }

        return match ($name) {
            "or", "in", "and" => $this->create("operator", $name),
            "true" => $this->create("value", true),
            "false" => $this->create("value", false),
            "null" => $this->create("value", null),
            default => $this->create("name", $name),
        };
    }

    public function tokens()
    {
        $tokens = [];
        $run = True;
        while ($run) {
            $token = $this->next();
            $tokens[] = $token;
            if ($token === null) {
                $run = false;
            }
        }
        return $tokens;
    }
}
