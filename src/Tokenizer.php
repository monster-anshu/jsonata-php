<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

use Exception;

class Tokenizer
{
    /**
     * @var string
     */
    private $path;
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

    public const escapes = [
        // JSON string escape sequences - see json.org
        '"' => '"',
        "\\" => "\\",
        "/" => "/",
        "b" => "\b",
        "f" => "\f",
        "n" => "\n",
        "r" => "\r",
        "t" => "\t"
    ];

    /**
     * @var int
     */
    private $position = 0;

    /**
     * @readonly
     * @var int
     */
    private $length;

    /**
     * @var int
     */
    private $depth = 0;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->length = strlen($this->path);
    }

    /**
     * @param mixed $value
     */
    private function create(string $type, $value): JsonataToken
    {
        return new JsonataToken(
            $type,
            $value,
            $this->position
        );
    }

    private function isClosingSlash(int $pos): bool
    {
        if ($pos < 0 || $pos >= $this->length) {
            return false;
        }

        if ($this->path[$pos] === '/' && $this->depth === 0) {
            $backslashCount = 0;
            $i = $pos - 1;
            while ($i >= 0 && $this->path[$i] === '\\') {
                ++$backslashCount;
                --$i;
            }

            return ($backslashCount % 2) === 0;
        }

        return false;
    }

    /**
     * Scan a JavaScript-like /regex/ with optional i,m flags (and implicit g).
     * @throws JException
     */
    private function scanRegex(): _Pattern
    {
        $start = $this->position;
        while ($this->position < $this->length) {
            $currentChar = $this->path[$this->position];

            if ($this->isClosingSlash($this->position)) {
                $pattern = substr($this->path, $start, $this->position - $start);
                if ($pattern === '') {
                    throw new JException("S0301", $this->position);
                }

                ++$this->position; // skip closing '/'
                $flagsStart = $this->position;

                // collect flags i/m (JSONata adds 'g' implicitly; we track it but PCRE doesn't need it)
                while ($this->position < $this->length) {
                    $c = $this->path[$this->position];
                    if ($c === 'i' || $c === 'm') {
                        ++$this->position;
                    } else {
                        break;
                    }
                }

                $flags = substr($this->path, $flagsStart, $this->position - $flagsStart) . 'g';

                $phpFlags = '';
                if (strpos($flags, 'i') !== false) {
                    $phpFlags .= 'i';
                }

                if (strpos($flags, 'm') !== false) {
                    $phpFlags .= 'm';
                }

                // Escape delimiter '/'
                $pcre = '/' . str_replace('/', '\/', $pattern) . '/' . $phpFlags;
                return new _Pattern($pcre);
            }

            // track bracket depth unless escaped
            $prev = $this->position > 0 ? $this->path[$this->position - 1] : null;
            if (($currentChar === '(' || $currentChar === '[' || $currentChar === '{') && $prev !== '\\') {
                ++$this->depth;
            }

            if (($currentChar === ')' || $currentChar === ']' || $currentChar === '}') && $prev !== '\\') {
                --$this->depth;
            }

            ++$this->position;
        }

        throw new JException("S0302", $this->position);
    }

    private function codepointToUtf8(int $codepoint): string
    {
        // minimal UTF-8 encoder
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        } elseif ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6)) .
                chr(0x80 | ($codepoint & 0x3F));
        } elseif ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12)) .
                chr(0x80 | (($codepoint >> 6) & 0x3F)) .
                chr(0x80 | ($codepoint & 0x3F));
        } else {
            return chr(0xF0 | ($codepoint >> 18)) .
                chr(0x80 | (($codepoint >> 12) & 0x3F)) .
                chr(0x80 | (($codepoint >> 6) & 0x3F)) .
                chr(0x80 | ($codepoint & 0x3F));
        }
    }

    /**
     * Returns next token, or null at end.
     * @throws JException
     * @param bool $prefix
     */
    public function next($prefix = false): ?JsonataToken
    {
        if ($this->position >= $this->length) {
            return null;
        }

        $currentChar = $this->path[$this->position];

        // skip whitespace
        while ($this->position < $this->length && strpos(" \t\n\r", $currentChar) !== false) {
            ++$this->position;
            if ($this->position >= $this->length) {
                return null;
            }

            $currentChar = $this->path[$this->position];
        }

        // skip comments /* ... */
        if ($this->position + 1 < $this->length && $currentChar === '/' && $this->path[$this->position + 1] === '*') {
            $commentStart = $this->position;
            $this->position += 2;
            while (
                $this->position + 1 < $this->length &&
                !($this->path[$this->position] === '*' && $this->path[$this->position + 1] === '/')
            ) {
                ++$this->position;
            }

            if ($this->position + 1 >= $this->length) {
                throw new JException("S0106", $commentStart);
            }

            $this->position += 2; // consume */
            return $this->next($prefix); // swallow following whitespace too
        }

        // test for regex (only when not in prefix position)
        if (!$prefix && $currentChar === '/') {
            ++$this->position; // consume initial '/'
            return $this->create("regex", $this->scanRegex());
        }

        $haveMore = $this->position < $this->length - 1;

        // handle double-char operators (and multi)
        $two = $haveMore ? $this->path[$this->position] . $this->path[$this->position + 1] : '';
        switch ($two) {
            case '..':
            case ':=':
            case '!=':
            case '>=':
            case '<=':
            case '**':
            case '~>':
            case '?:':
            case '??':
                $this->position += 2;
                return $this->create("operator", $two);
        }

        // single-char operators
        if (array_key_exists($currentChar, self::operators)) {
            ++$this->position;
            return $this->create("operator", $currentChar);
        }

        // string literals (' or ")
        if ($currentChar === '"' || $currentChar === "'") {
            $quoteType = $currentChar;
            ++$this->position; // skip opening quote
            $qstr = '';
            while ($this->position < $this->length) {
                $c = $this->path[$this->position];
                if ($c === '\\') { // escape
                    ++$this->position;
                    if ($this->position >= $this->length) {
                        throw new JException("S0301", $this->position);
                    }

                    $esc = $this->path[$this->position];
                    if (array_key_exists($esc, self::escapes)) {
                        $qstr .= self::escapes[$esc];
                    } elseif ($esc === 'u') {
                        // \uXXXX
                        if ($this->position + 4 >= $this->length) {
                            throw new JException("S0104", $this->position);
                        }

                        $octets = substr($this->path, $this->position + 1, 4);
                        if (preg_match('/^[0-9a-fA-F]{4}$/', $octets) !== 1) {
                            throw new JException("S0104", $this->position);
                        }

                        $codepoint = intval($octets, 16);
                        $qstr .= $this->codepointToUtf8($codepoint);
                        $this->position += 4;
                    } else {
                        throw new JException("S0301", $this->position, $esc);
                    }
                } elseif ($c === $quoteType) {
                    ++$this->position; // consume closing quote
                    return $this->create("string", $qstr);
                } else {
                    $qstr .= $c;
                }

                ++$this->position;
            }

            throw new JException("S0101", $this->position);
        }

        // numbers
        $rest = substr($this->path, $this->position);
        if (preg_match('/^-?(0|([1-9]\d*))(\.\d+)?([Ee][-+]?\d+)?/', $rest, $m) === 1) {
            $lexeme = $m[0];
            $num = (float) $lexeme;
            if (!is_nan($num) && is_finite($num)) {
                $this->position += strlen($lexeme);
                return $this->create("number", Utils::convertNumber($num));
            }

            throw new JException("S0102", $this->position);
        }

        // quoted names with backticks
        if ($currentChar === '`') {
            ++$this->position;
            $end = strpos($this->path, '`', $this->position);
            if ($end !== false) {
                $name = substr($this->path, $this->position, $end - $this->position);
                $this->position = $end + 1;
                return $this->create("name", $name);
            }

            $this->position = $this->length;
            throw new JException("S0105", $this->position);
        }

        // names/variables/keywords
        $i = $this->position;
        while (true) {
            $ch = ($i < $this->length) ? $this->path[$i] : "\0";
            // stop at end, whitespace, or any single-char operator
            if ($i === $this->length || strpos(" \t\n\r", $ch) !== false || array_key_exists($ch, self::operators)) {
                if ($this->path[$this->position] === '$') {
                    // variable
                    $name = substr($this->path, $this->position + 1, $i - ($this->position + 1));
                    $this->position = $i;
                    return $this->create("variable", $name);
                } else {
                    $name = substr($this->path, $this->position, $i - $this->position);
                    $this->position = $i;
                    switch ($name) {
                        case "or":
                        case "in":
                        case "and":
                            return $this->create("operator", $name);
                        case "true":
                            return $this->create("value", true);
                        case "false":
                            return $this->create("value", false);
                        case "null":
                            return $this->create("value", null);
                        default:
                            if ($this->position === $this->length && $name === "") {
                                return null; // trailing whitespace
                            }

                            return $this->create("name", $name);
                    }
                }
            } else {
                ++$i;
            }
        }
    }


    public function tokens()
    {
        $tokens = [];
        $run = true;
        while ($run) {
            $token = $this->next();
            $tokens[] = $token;
            if (!$token instanceof \Monster\JsonataPhp\JsonataToken) {
                $run = false;
            }
        }

        return $tokens;
    }
}
