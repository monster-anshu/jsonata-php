<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

use ValueError;

final class Functions
{
    /**
     * Count function
     * @param array|null $args - Arguments
     * @return int Number of elements in the array
     */
    public static function count(?array $args): int
    {
        if ($args === null) {
            return 0;
        }

        return count($args);
    }

    /**
     * Max function
     * @param array|null $args - Arguments
     * @return float|int|null Max element in the array
     */
    public static function max(?array $args)
    {
        if ($args === null || $args === []) {
            return null;
        }

        return max(array_map(function ($n) {
            return (float) $n;
        }, $args));
    }

    /**
     * Min function
     * @param array|null $args - Arguments
     * @return float|int|null Min element in the array
     */
    public static function min(?array $args)
    {
        if ($args === null || $args === []) {
            return null;
        }

        return min(array_map(function ($n) {
            return (float) $n;
        }, $args));
    }

    /**
     * Average function
     * @param array|null $args - Arguments
     * @return float|null Average element in the array
     */
    public static function average(?array $args): ?float
    {
        if ($args === null || $args === []) {
            return null;
        }

        return array_sum(array_map(function ($n) {
            return (float) $n;
        }, $args)) / count($args);
    }

    /**
     * Sum function
     * @param array|null $args - Arguments
     */
    public static function sum(?array $args): ?float
    {
        if ($args === null) {
            return null;
        }

        return array_sum(array_map(function ($n) {
            return (float) $n;
        }, $args));
    }

    /**
     * Stringify arguments
     * @param mixed $arg - Arguments
     * @param bool|null $prettify - Pretty print the result
     */
    public static function string($arg, ?bool $prettify = false): ?string
    {
        if ($arg === null) {
            return null;
        }

        if (is_string($arg)) {
            return $arg;
        }

        return self::stringify($arg, $prettify ?? false, "");
    }

    /**
     * Internal recursive string function
     */
    private static function stringify($arg, bool $prettify, string $indent): string
    {
        if ($arg === null) {
            return "null";
        }

        // Numbers and booleans
        if (is_int($arg) || is_float($arg) || is_bool($arg)) {
            return (string) $arg;
        }

        // Strings
        if (is_string($arg)) {
            return json_encode($arg, JSON_UNESCAPED_UNICODE);
        }

        // Associative arrays = Map
        if (Utils::isAssoc($arg)) {
            $parts = [];
            foreach ($arg as $k => $v) {
                $val = self::stringify($v, $prettify, $indent . "  ");
                $parts[] = json_encode((string) $k) . ":" . ($prettify ? " " : "") . $val;
            }

            if ($prettify) {
                return "{\n" . $indent . "  " . implode(",\n" . $indent . "  ", $parts) . "\n" . $indent . "}";
            }

            return "{" . implode(",", $parts) . "}";
        }

        // Indexed arrays = List
        if (Utils::isArray($arg)) {
            $parts = [];
            foreach ($arg as $v) {
                $parts[] = self::stringify($v, $prettify, $indent . "  ");
            }

            if ($prettify) {
                return "[\n" . $indent . "  " . implode(",\n" . $indent . "  ", $parts) . "\n" . $indent . "]";
            }

            return "[" . implode(",", $parts) . "]";
        }

        throw new ValueError(
            "Only JSON types (values, Map, List) can be stringified. Unsupported type: " . gettype($arg)
        );
    }

    /**
     * Validate input data types
     * @param mixed $arg
     */
    public static function validateInput($arg): void
    {

        if ($arg === null) {
            return;
        }

        if (is_int($arg) || is_float($arg) || is_bool($arg) || is_string($arg)) {
            return;
        }

        if (Utils::isAssoc($arg)) {
            foreach ($arg as $k => $v) {
                self::validateInput($k);
                self::validateInput($v);
            }

            return;
        }

        if (Utils::isArray($arg)) {
            foreach ($arg as $v) {
                self::validateInput($v);
            }

            return;
        }

        throw new ValueError(
            "Only JSON types (values, Map, List) are allowed as input. Unsupported type: " . gettype($arg)
        );
    }

    /**
     * Create substring based on character number and length
     * @param string|null $str - String to evaluate
     * @param int|null $start - Character number to start substring
     * @param int|null $length - Number of characters in substring
     * @return string|null Substring
     */
    public static function substring(?string $str, ?int $start, ?int $length = null): ?string
    {
        if ($str === null) {
            return null;
        }

        $strLength = mb_strlen($str, 'UTF-8');

        if ($strLength + $start < 0) {
            $start = 0;
        }

        if ($length !== null) {
            if ($length <= 0) {
                return "";
            }

            return self::substr($str, $start, $length);
        }

        return self::substr($str, $start, $strLength);
    }

    /**
     * Substring with Unicode support
     */
    public static function substr(string $str, int $start, ?int $length = null): string
    {
        $strLength = mb_strlen($str, 'UTF-8');

        if ($start >= $strLength) {
            return "";
        }

        // Negative start → from the end
        if ($start < 0) {
            $start = max(0, $strLength + $start);
        }

        if ($length === null) {
            return mb_substr($str, $start, null, 'UTF-8');
        } elseif ($length <= 0) {
            return "";
        } else {
            return mb_substr($str, $start, $length, 'UTF-8');
        }
    }

    /**
     * Substring before chars
     */
    public static function substringBefore(?string $str, ?string $chars): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($chars === null) {
            return $str;
        }

        $pos = mb_strpos($str, $chars, 0, 'UTF-8');
        return $pos !== false ? mb_substr($str, 0, $pos, 'UTF-8') : $str;
    }

    /**
     * Substring after chars
     */
    public static function substringAfter(?string $str, ?string $chars): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($chars === null) {
            return $str;
        }

        $pos = mb_strpos($str, $chars, 0, 'UTF-8');
        return $pos !== false ? mb_substr($str, $pos + mb_strlen($chars, 'UTF-8'), null, 'UTF-8') : $str;
    }

    /**
     * Lowercase a string
     */
    public static function lowercase(?string $str): ?string
    {
        return $str === null ? null : mb_strtolower($str, 'UTF-8');
    }

    /**
     * Uppercase a string
     */
    public static function uppercase(?string $str): ?string
    {
        return $str === null ? null : mb_strtoupper($str, 'UTF-8');
    }

    /**
     * Length of a string (Unicode-aware)
     */
    public static function length(?string $str): ?int
    {
        return $str === null ? null : mb_strlen($str, 'UTF-8');
    }

    /**
     * Normalize and trim whitespace
     */
    public static function trim(?string $str): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($str === "") {
            return "";
        }

        // normalize multiple whitespace to single space
        $result = preg_replace('/[ \t\n\r]+/u', ' ', $str);

        // strip leading/trailing spaces
        return trim((string) $result, " ");
    }

    /**
     * Pad string
     */
    public static function pad(?string $str, int $width, ?string $char = " "): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($char === null || $char === "") {
            $char = " ";
        }

        return $width < 0
            ? self::leftPad($str, -$width, $char)
            : self::rightPad($str, $width, $char);
    }

    public static function leftPad(?string $str, int $size, ?string $padStr = " "): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($padStr === null || $padStr === "") {
            $padStr = " ";
        }

        $strLen = mb_strlen($str, 'UTF-8');
        $pads = $size - $strLen;
        if ($pads <= 0) {
            return $str;
        }

        $padding = str_repeat($padStr, $pads + 1);
        return self::substr($padding, 0, $pads) . $str;
    }

    public static function rightPad(?string $str, int $size, ?string $padStr = " "): ?string
    {
        if ($str === null) {
            return null;
        }

        if ($padStr === null || $padStr === "") {
            $padStr = " ";
        }

        $strLen = mb_strlen($str, 'UTF-8');
        $pads = $size - $strLen;
        if ($pads <= 0) {
            return $str;
        }

        $padding = str_repeat($padStr, $pads + 1);
        return $str . self::substr($padding, 0, $pads);
    }

    /**
     * Regex match (like evaluateMatcher)
     */
    public static function evaluateMatcher(string $pattern, string $str): array
    {
        $matches = [];
        preg_match_all($pattern, $str, $allMatches, PREG_OFFSET_CAPTURE);
        // group 0 = whole match, rest are capture groups
        $counter = count($allMatches[0]);

        // group 0 = whole match, rest are capture groups
        for ($i = 0; $i < $counter; ++$i) {
            $matchData = [
                "match" => $allMatches[0][$i][0],
                "index" => $allMatches[0][$i][1],
                "groups" => []
            ];
            for ($g = 1; $g < count($allMatches); ++$g) {
                $matchData["groups"][] = $allMatches[$g][$i][0] ?? null;
            }

            $matches[] = $matchData;
        }

        return $matches;
    }

    /**
     * Tests if the string contains the token
     * @param string|_Pattern $token
     */
    public static function contains(?string $str, $token): ?bool
    {
        if ($str === null) {
            return null;
        }

        if (is_string($token)) {
            return strpos($str, $token) !== false;
        } elseif ($token instanceof _Pattern || $token instanceof \stdClass) {
            // For PHP, we just use preg_match
            return $token->matches($str);
        } else {
            throw new \InvalidArgumentException("unknown type to match: " . gettype($token));
        }
    }


    /**
     * Base64 encode
     */
    public static function base64encode(?string $str): ?string
    {
        return $str === null ? null : base64_encode($str);
    }

    /**
     * Base64 decode
     */
    public static function base64decode(?string $str): ?string
    {
        return $str === null ? null : base64_decode($str, true);
    }

    /**
     * Encode a string into a URL component
     */
    public static function encodeUrlComponent(?string $str): ?string
    {
        return $str === null ? null : rawurlencode($str);
    }

    /**
     * Encode a string into a URL
     */
    public static function encodeUrl(?string $str): ?string
    {
        return $str === null ? null : urlencode($str);
    }

    /**
     * Decode a URL component
     */
    public static function decodeUrlComponent(?string $str): ?string
    {
        return $str === null ? null : rawurldecode($str);
    }

    /**
     * Decode a URL
     */
    public static function decodeUrl(?string $str): ?string
    {
        return $str === null ? null : urldecode($str);
    }

    /**
     * Split a string by a pattern or string
     * @param string $pattern
     * @return array<string>|null
     */
    public static function split(?string $str, $pattern, ?int $limit = null): ?array
    {
        if ($str === null) {
            return null;
        }

        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException("Limit must be >= 0");
        }

        if (is_string($pattern)) {
            if ($pattern === "") {
                return $limit === 0 ? [] : preg_split('//u', $str, $limit ?: -1, PREG_SPLIT_NO_EMPTY);
            } else {
                return explode($pattern, $str, $limit ?: PHP_INT_MAX);
            }
        } else {
            // regex split
            return preg_split($pattern, $str, $limit ?: -1);
        }
    }

    /**
     * Replace (safe version with optional limit)
     */
    public static function replace(?string $str, $pattern, $replacement, ?int $limit = null): ?string
    {
        if ($str === null) {
            return null;
        }

        if (is_string($pattern)) {
            if ($pattern === "") {
                throw new \InvalidArgumentException("Second argument of replace cannot be an empty string");
            }

            if ($limit === null) {
                return str_replace($pattern, $replacement, $str);
            } else {
                return preg_replace('/' . preg_quote($pattern, '/') . '/', (string) $replacement, $str, $limit);
            }
        } else {
            return preg_replace($pattern, (string) $replacement, $str, $limit ?? -1);
        }
    }


    /**
     * Match a string with regex returning array of match details
     */
    public static function match(?string $str, string $pattern, ?int $limit = null): ?array
    {
        if ($str === null) {
            return null;
        }

        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException("Limit must be non-negative");
        }

        preg_match_all($pattern, $str, $matches, PREG_OFFSET_CAPTURE);
        $result = [];

        $count = count($matches[0]);
        $max = $limit ?? $count;

        for ($i = 0; $i < $count && $i < $max; ++$i) {
            $groups = [];
            foreach ($matches as $match) {
                $groups[] = $match[$i][0];
            }

            $result[] = [
                'match' => $matches[0][$i][0],
                'index' => $matches[0][$i][1],
                'groups' => $groups
            ];
        }

        return $result;
    }

    /**
     * Join array of strings
     */
    public static function join(?array $strs, string $separator = ''): ?string
    {
        if ($strs === null) {
            return null;
        }

        return implode($separator, $strs);
    }

    public static function formatBase(?float $value, ?int $_radix = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = self::round($value, 0);
        $radix = $_radix ?? 10;

        if ($radix < 2 || $radix > 36) {
            throw new JException("D3100", $radix);
        }

        return base_convert((string) $value, 10, $radix);
    }

    public static function number($arg): ?float
    {
        if ($arg === null) {
            return null;
        }

        if ($arg === Utils::$none) {
            throw new JException("T0410", -1);
        }

        if (is_numeric($arg)) {
            return $arg + 0;
        }

        if (is_string($arg)) {
            if (strncmp($arg, "0x", strlen("0x")) === 0) {
                return hexdec(substr($arg, 2));
            }

            if (strncmp($arg, "0B", strlen("0B")) === 0) {
                return bindec(substr($arg, 2));
            }

            if (strncmp($arg, "0O", strlen("0O")) === 0) {
                return octdec(substr($arg, 2));
            }

            return (float) $arg;
        }

        if (is_bool($arg)) {
            return $arg ? 1 : 0;
        }

        throw new ValueError("Cannot cast to number");
    }

    public static function abs(?float $arg): ?float
    {
        return $arg === null ? null : abs($arg);
    }

    public static function floor(?float $arg): ?float
    {
        return $arg === null ? null : floor($arg);
    }

    public static function ceil(?float $arg): ?float
    {
        return $arg === null ? null : ceil($arg);
    }

    public static function round(?float $arg, ?int $precision = 0): ?float
    {
        return $arg === null ? null : round($arg, $precision, PHP_ROUND_HALF_EVEN);
    }

    public static function sqrt(?float $arg): ?float
    {
        if ($arg === null) {
            return null;
        }

        if ($arg < 0) {
            throw new JException("D3060", 1, $arg);
        }

        return sqrt($arg);
    }

    public static function power(?float $arg, float $exp): ?float
    {
        if ($arg === null) {
            return null;
        }

        $result = $arg ** $exp;
        if (!is_finite($result)) {
            throw new JException("D3061", 1, $arg, $exp);
        }

        return $result;
    }

    public static function random(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    public static function toBoolean($arg): ?bool
    {
        if ($arg === null) {
            return null;
        }

        if (Utils::isArray($arg)) {
            if (count($arg) === 1) {
                return self::toBoolean($arg[0]);
            }

            return array_filter((array) $arg, function ($e) {
                return (bool) $e;
            }) !== [];
        }

        if (is_string($arg)) {
            return strlen($arg) > 0;
        }

        if (is_numeric($arg)) {
            return $arg != 0;
        }

        if (is_bool($arg)) {
            return $arg;
        }

        if (is_object($arg) || is_array($arg)) {
            return (array) $arg !== [];
        }

        return false;
    }


    public static function format_number(?float $value, ?string $picture, ?array $decimal_format = null): ?string
    {
        if ($decimal_format === null) {
            $decimal_format = [];
        }

        $pattern_separator = $decimal_format['pattern-separator'] ?? ';';
        $sub_pictures = explode($pattern_separator, (string) $picture);

        if (count($sub_pictures) > 2) {
            throw new JException('D3080', -1);
        }

        $decimal_separator = $decimal_format['decimal-separator'] ?? '.';
        foreach ($sub_pictures as $p) {
            if (substr_count($p, $decimal_separator) > 1) {
                throw new JException('D3081', -1);
            }
        }

        $percent_sign = $decimal_format['percent'] ?? '%';
        $per_mille_sign = $decimal_format['per-mille'] ?? '‰';

        foreach ($sub_pictures as $p) {
            if (substr_count($p, $percent_sign) > 1) {
                throw new JException('D3082', -1);
            }

            if (substr_count($p, $per_mille_sign) > 1) {
                throw new JException('D3083', -1);
            }

            if (substr_count($p, $percent_sign) + substr_count($p, $per_mille_sign) > 1) {
                throw new JException('D3084');
            }
        }

        $zero_digit = $decimal_format['zero-digit'] ?? '0';
        $optional_digit = $decimal_format['digit'] ?? '#';
        $digits_family = '';
        for ($i = 0; $i < 10; ++$i) {
            $digits_family .= chr(ord($zero_digit) + $i);
        }

        foreach ($sub_pictures as $p) {
            if (strpos($p, $optional_digit) === false && strpbrk($p, $digits_family) === false) {
                throw new JException('D3085', -1);
            }
        }

        $grouping_separator = $decimal_format['grouping-separator'] ?? ',';
        $adjacent_pattern = '/[' . preg_quote($grouping_separator, '/') . preg_quote($decimal_separator, '/') . ']{2}/';
        foreach ($sub_pictures as $sub_picture) {
            if (preg_match($adjacent_pattern, $sub_picture)) {
                throw new JException('D3087', -1);
            }
        }

        foreach ($sub_pictures as $sub_picture) {
            $parts = explode($decimal_separator, $sub_picture);
            foreach ($parts as $part) {
                if (substr_compare($part, $grouping_separator, -strlen($grouping_separator)) === 0) {
                    throw new JException('D3088', -1);
                }
            }
        }

        $active_characters = $digits_family . $decimal_separator . $grouping_separator . $pattern_separator . $optional_digit;

        if ($value === null) {
            return null;
        }

        if (is_nan($value)) {
            return $decimal_format['NaN'] ?? 'NaN';
        }

        if (!is_numeric($value)) {
            $value = (float) $value;
        }

        $minus_sign = $decimal_format['minus-sign'] ?? '-';

        // Pick sub-picture for positive/negative
        $prefix = '';
        if ($value >= 0) {
            $subpic = $sub_pictures[0];
        } else {
            $subpic = $sub_pictures[count($sub_pictures) - 1];
            if (count($sub_pictures) == 1) {
                $prefix = $minus_sign;
            }
        }

        // Separate prefix from digits
        $found = false;
        $len = strlen($subpic);
        for ($k = 0; $k < $len; ++$k) {
            $ch = $subpic[$k];
            if (strpos($active_characters, $ch) !== false) {
                $prefix .= substr($subpic, 0, $k);
                $subpic = substr($subpic, $k);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $prefix .= $subpic;
            $subpic = '';
        }

        // Determine suffix for percent/per-mille
        if ($subpic === '') {
            $suffix = '';
        } elseif (substr_compare($subpic, $percent_sign, -strlen($percent_sign)) === 0) {
            $suffix = $percent_sign;
            $subpic = substr($subpic, 0, -strlen($percent_sign));
            $value *= 100;
        } elseif (substr_compare($subpic, $per_mille_sign, -strlen($per_mille_sign)) === 0) {
            $suffix = $per_mille_sign;
            $subpic = substr($subpic, 0, -strlen($per_mille_sign));
            $value *= 1000;
        } else {
            // Find suffix dynamically
            $suffix = '';
            $len = strlen($subpic);
            for ($k = $len - 1; $k >= 0; --$k) {
                $ch = $subpic[$k];
                if (strpos($active_characters, $ch) !== false) {
                    $suffix = substr($subpic, $k + 1);
                    $subpic = substr($subpic, 0, $k + 1);
                    break;
                }
            }
        }

        // Split integer and fractional parts
        $fmt_tokens = explode($decimal_separator, $subpic);
        if (empty($fmt_tokens[0]) && (!isset($fmt_tokens[1]) || $fmt_tokens[1] === '')) {
            throw new JException('both integer and fractional parts are empty', -1);
        }

        if (is_infinite($value)) {
            return $prefix . ($decimal_format['infinity'] ?? '∞') . $suffix;
        }

        // Convert to string and format digits
        $chunks = Functions::decimal_to_string($value);
        $chunks = explode('.', ltrim($chunks, '-'));

        $result = Functions::format_digits($chunks[0], $fmt_tokens[0], $digits_family, $optional_digit, $grouping_separator);

        if (isset($fmt_tokens[1]) && $fmt_tokens[1] !== '') {
            if (!isset($chunks[1])) {
                $chunks[1] = $zero_digit;
            }

            $decimal_part = Functions::format_digits($chunks[1], $fmt_tokens[1], $digits_family, $optional_digit, $grouping_separator);
            $result .= $decimal_separator . $decimal_part;
        }

        return $prefix . $result . $suffix;
    }



    /**
     * Convert a decimal value to a string without exponent notation.
     */
    public static function decimal_to_string(float $value): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $str = (string) $value;

        if (strpos($str, 'E') !== false || strpos($str, 'e') !== false) {
            // Convert scientific notation to decimal string
            $parts = explode('E', strtoupper($str));
            $base = rtrim($parts[0], '.');
            $exp = (int) $parts[1];

            if (($dotPos = strpos($base, '.')) !== false) {
                $fracLen = strlen($base) - $dotPos - 1;
                $base = str_replace('.', '', $base);
                $exp -= $fracLen;
            }

            if ($exp >= 0) {
                $str = $base . str_repeat('0', $exp);
            } else {
                $str = substr($base, 0, $exp) ?: '0';
                $str .= '.';
                $str .= substr($base, $exp) ?: str_repeat('0', -$exp - strlen($base)) . $base;
            }
        }

        return $negative ? '-' . $str : $str;
    }

    /**
     * Format digits according to a picture pattern.
     */
    public static function format_digits(
        string $digits,
        string $fmt,
        string $digits_family = '0123456789',
        string $optional_digit = '#',
        ?string $grouping_separator = null
    ): string {
        $result = [];
        $digits_arr = str_split(strrev($digits));
        $num_digit = array_shift($digits_arr);

        $fmt_arr = str_split(strrev($fmt));
        foreach ($fmt_arr as $fmt_char) {
            if (strpos($digits_family, $fmt_char) !== false || $fmt_char === $optional_digit) {
                if ($num_digit !== null && $num_digit !== '') {
                    $result[] = $digits_family[ord($num_digit) - 48];
                    $num_digit = array_shift($digits_arr) ?? '';
                } elseif ($fmt_char !== $optional_digit) {
                    $result[] = $digits_family[0];
                }
            } elseif ((!$result || !in_array(end($result), str_split($digits_family))) && $grouping_separator && end($result) !== $grouping_separator) {
                throw new JException("invalid grouping in picture argument", -1);
            } else {
                $result[] = $fmt_char;
            }
        }

        if ($num_digit !== null && $num_digit !== '') {
            // Handle remaining digits (without repeating separator logic for simplicity)
            while ($num_digit !== null && $num_digit !== '') {
                $result[] = $digits_family[ord($num_digit) - 48];
                $num_digit = array_shift($digits_arr) ?? '';
            }
        }

        $res_str = implode('', array_reverse($result));
        if ($grouping_separator) {
            $res_str = ltrim($res_str, $grouping_separator);
        }

        return $res_str;
    }



    /**
     * Returns the Boolean NOT of the arg
     * @param mixed $arg
     */
    public static function not($arg): ?bool
    {
        if ($arg === null) {
            return null;
        }

        return !self::toBoolean($arg);
    }

    /**
     * @param mixed $func
     */
    public static function getFunctionArity($func): int
    {
        if ($func instanceof _JFunction) {
            return $func->signature->getMinNumberOfArgs();
        } else { // Lambda
            return count($func->arguments);
        }
    }

    /**
     * Build the arguments list for HOFs like map, filter, each
     * @param mixed $func
     * @param mixed $arg1
     * @param mixed $arg2
     * @param mixed $arg3
     */
    public static function hofFuncArgs($func, $arg1, $arg2 = null, $arg3 = null): array
    {
        $func_args = [$arg1];
        $length = self::getFunctionArity($func);
        if ($length >= 2) {
            $func_args[] = $arg2;
        }

        if ($length >= 3) {
            $func_args[] = $arg3;
        }

        return $func_args;
    }

    /**
     * Apply a function with arguments
     * @param mixed $func
     * @return mixed
     */
    public static function funcApply($func, array $funcArgs)
    {
        if (self::isLambda($func)) {
            $current = Jsonata::current();
            return $current->apply($func, $funcArgs, null, $current->environment);
        } else {
            /**@var _JFunction */
            $fn = $func;
            return $fn->call(null, $funcArgs);
        }
    }

    /**
     * Map an array using a function
     * @param mixed $func
     */
    public static function map(?array $arr, $func): ?array
    {
        if ($arr === null) {
            return null;
        }

        $result = [];
        foreach ($arr as $i => $arg) {
            $funcArgs = self::hofFuncArgs($func, $arg, $i, $arr);
            $res = self::funcApply($func, $funcArgs);
            if ($res !== null) {
                $result[] = $res;
            }
        }

        return $result;
    }

    /**
     * Filter an array using a predicate function
     * @param mixed $func
     */
    public static function filter(?array $arr, $func): ?array
    {
        if ($arr === null) {
            return null;
        }

        $result = [];
        foreach ($arr as $i => $entry) {
            $funcArgs = self::hofFuncArgs($func, $entry, $i, $arr);
            $res = self::funcApply($func, $funcArgs);
            if (self::toBoolean($res)) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Return the single element matching a condition
     * Throws exception if not exactly one match
     * @param mixed $func
     * @return mixed
     */
    public static function single(?array $arr, $func = null)
    {
        if ($arr === null) {
            return null;
        }

        $found = false;
        $result = null;
        foreach ($arr as $i => $entry) {
            $positive = true;
            if ($func !== null) {
                $funcArgs = self::hofFuncArgs($func, $entry, $i, $arr);
                $res = self::funcApply($func, $funcArgs);
                $positive = self::toBoolean($res);
            }

            if ($positive) {
                if (!$found) {
                    $result = $entry;
                    $found = true;
                } else {
                    throw new JException("D3138", $i);
                }
            }
        }

        if (!$found) {
            throw new JException("D3139", -1);
        }

        return $result;
    }

    /**
     * Zip arrays together
     */
    public static function zip(array $args): array
    {
        $result = [];
        $length = PHP_INT_MAX;
        $nargs = count($args);
        foreach ($args as $arg) {
            if ($arg === null) {
                $length = 0;
                break;
            }

            $length = min($length, count($arg));
        }

        for ($i = 0; $i < $length; ++$i) {
            $tuple = [];
            for ($k = 0; $k < $nargs; ++$k) {
                $tuple[] = $args[$k][$i];
            }

            $result[] = $tuple;
        }

        return $result;
    }


    /**
     * @param array<mixed>|null $sequence
     * @param mixed $func
     * @throws \Throwable
     * @param mixed $init
     * @return mixed
     */
    public static function foldLeft(?array $sequence, $func, $init)
    {
        if ($sequence === null) {
            return null;
        }

        $result = null;

        $arity = self::getFunctionArity($func);
        if ($arity < 2) {
            throw new JException("D3050", 1);
        }

        if ($init === null && $sequence !== []) {
            $result = $sequence[0];
            $index = 1;
        } else {
            $result = $init;
            $index = 0;
        }

        while ($index < count($sequence)) {
            $args = [$result, $sequence[$index]];
            if ($arity >= 3) {
                $args[] = $index;
            }

            if ($arity >= 4) {
                $args[] = $sequence;
            }

            $result = self::funcApply($func, $args);
            ++$index;
        }

        return $result;
    }

    /**
     * @return array<mixed>
     * @param mixed $arg
     */
    public static function keys($arg): array
    {
        $result = Utils::createSequence();

        if (is_array($arg)) {
            $keys = [];
            foreach ($arg as $el) {
                $keys = array_merge($keys, self::keys($el));
            }

            $result = array_values(array_unique(array_merge($result->toArray(), $keys)));
        } elseif ($arg instanceof \ArrayObject || is_object($arg)) {
            $result = array_merge($result->toArray(), array_keys((array) $arg));
        }

        return $result;
    }

    /**
     * @param mixed $arg
     */
    public static function exists($arg): bool
    {
        return $arg !== null;
    }

    /**
     * @param mixed $arg
     * @return mixed
     */
    public static function spread($arg)
    {
        $result = Utils::createSequence();

        if (is_array($arg)) {
            foreach ($arg as $item) {
                $result = self::append($result, self::spread($item));
            }
        } elseif ($arg instanceof \ArrayObject || is_array($arg)) {
            foreach ($arg as $key => $val) {
                $obj = [$key => $val];
                $result[] = $obj;
            }
        } else {
            return $arg;
        }

        return $result;
    }

    /**
     * @param array<array<string, mixed>>|null $arg
     * @return array<string, mixed>|null
     */
    public static function merge(?array $arg): ?array
    {
        if ($arg === null) {
            return null;
        }

        $result = [];
        foreach ($arg as $obj) {
            foreach ($obj as $key => $val) {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed>|null $arr
     * @return array<mixed>|null
     */
    public static function reverse(?array $arr): ?array
    {
        if ($arr === null) {
            return null;
        }

        if (count($arr) <= 1) {
            return $arr;
        }

        return array_reverse($arr);
    }

    /**
     * @param array<string,mixed>|null $obj
     * @param mixed $func
     * @throws \Throwable
     */
    public static function each(?array $obj, $func): ?JList
    {
        if ($obj === null) {
            return null;
        }

        $jList = Utils::createSequence();
        foreach ($obj as $key => $val) {
            $func_args = self::hofFuncArgs($func, $val, $key, $obj);
            $res = self::funcApply($func, $func_args);
            if ($res !== null) {
                $jList[] = $res;
            }
        }

        return $jList;
    }

    /**
     * @throws \Throwable
     * @return never
     */
    public static function error(?string $message)
    {
        throw new JException("D3137", -1, $message ?? "\$error() function evaluated");
    }

    /**
     * @throws \Throwable
     */
    public static function assertFn(bool $condition): void
    {
        if (!$condition) {
            throw new JException("D3141", -1, "\$assert() statement failed");
        }
    }

    /**
     * @param mixed $value
     */
    public static function type($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === Utils::$none) {
            return "null";
        }

        if (is_int($value) || is_float($value)) {
            return "number";
        }

        if (is_string($value)) {
            return "string";
        }

        if (is_bool($value)) {
            return "boolean";
        }

        if (Utils::isArray($value)) {
            return "array";
        }

        if (Utils::isFunction($value) || self::isLambda($value)) {
            return "function";
        }

        return "object";
    }

    /**
     * @param array<mixed>|null $arr
     * @param mixed $comparator
     * @return array<mixed>|null
     */
    public static function sort(?array $arr, $comparator): ?array
    {
        if ($arr === null) {
            return null;
        }

        if (count($arr) <= 1) {
            return $arr;
        }

        $result = $arr;

        if ($comparator !== null) {
            // TODO: implement usort with comparator
            // usort($result, function ($o1, $o2) use ($comparator) {
            //     $swap = self::funcApply($comparator, [$o1, $o2]);
            //     return $swap ? 1 : -1;
            // });
        } else {
            sort($result);
        }

        return $result;
    }

    /**
     * @param array<mixed>|null $arr
     * @return array<mixed>|null
     */
    public static function shuffle(?array $arr): ?array
    {
        if ($arr === null) {
            return null;
        }

        if (count($arr) <= 1) {
            return $arr;
        }

        $result = $arr;
        shuffle($result);
        return $result;
    }

    /**
     * @param mixed $arr
     * @return array<mixed>|null
     */
    public static function distinct($arr)
    {
        if ($arr === null) {
            return null;
        }

        if (!Utils::isArray($arr) || count($arr) <= 1) {
            return $arr;
        }

        $results = $arr instanceof JList ? Utils::createSequence() : [];
        $set = array_unique($arr, SORT_REGULAR);

        return array_merge($results, $set);
    }

    /**
     * @param array<string,mixed>|null $arg
     * @param mixed $func
     * @return array<string,mixed>|null
     * @throws \Throwable
     */
    public static function sift(?array $arg, $func): ?array
    {
        if ($arg === null) {
            return null;
        }

        $result = [];
        foreach ($arg as $key => $entry) {
            $func_args = self::hofFuncArgs($func, $entry, $key, $arg);
            $res = self::funcApply($func, $func_args);
            if (Jsonata::boolize($res)) {
                $result[$key] = $entry;
            }
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }


    /**
     * Append second argument to first
     * @param mixed $arg1
     * @param mixed $arg2
     * @return mixed
     */
    public static function append($arg1, $arg2)
    {
        if ($arg1 === null) {
            return $arg2;
        }

        if ($arg2 === null) {
            return $arg1;
        }

        if (!Utils::isArray($arg1)) {
            $arg1 = Utils::createSequence($arg1);
        }

        if (!Utils::isArray($arg2)) {
            $arg2 = new JList([$arg2]);
        }

        if (empty($arg1) && ($arg2 instanceof RangeList)) {
            return $arg2;
        }

        $arg1 = new JList($arg1);
        foreach ($arg2 as $item) {
            $arg1[] = $item;
        }

        return $arg1;
    }

    /**
     * @param mixed $result
     */
    public static function isLambda($result): bool
    {
        return ($result instanceof Symbol && $result->_jsonata_lambda);
    }

    /**
     * Return value from an object for a given key
     * @param mixed $input
     * @return mixed
     */
    public static function lookup($input, string $key)
    {
        $result = null;
        if (Utils::isArray($input)) {
            $result = Utils::createSequence();
            foreach ($input as $item) {
                $res = self::lookup($item, $key);
                if ($res !== null) {
                    if (Utils::isArray($res)) {
                        $result = array_merge((array) $result, (array) $res);
                    } else {
                        $result[] = $res;
                    }
                }
            }
        } elseif (Utils::isAssoc($input)) {
            $arr = (array) $input;
            $result = $arr[$key] ?? null;
            if ($result === null && array_key_exists($key, $arr)) {
                $result = Utils::$none;
            }
        }

        return $result;
    }

    public static function test(string $a, string $b): string
    {
        return $a . $b;
    }

    public static function getFunction(string $class, string $name): ?\ReflectionMethod
    {
        $methods = (new \ReflectionClass(Functions::class))->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($method->getName() === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param mixed $instance
     * @return mixed
     */
    public static function call(string $class, $instance, string $name, array $args)
    {
        return self::callMethod($instance, self::getFunction($class, $name), $args);
    }

    /**
     * @param mixed $instance
     * @return mixed
     */
    public static function callMethod($instance, ?\ReflectionMethod $reflectionMethod, array $args)
    {
        if (!$reflectionMethod instanceof \ReflectionMethod) {
            throw new \Exception("Method not found");
        }

        $params = $reflectionMethod->getParameters();
        $nargs = count($params);

        $callArgs = $args;
        while (count($callArgs) < $nargs) {
            $callArgs[] = null;
        }

        if ($nargs > 0 && $params[0]->getType() && $params[0]->getType()->getName() === 'array' && !Utils::isArray($callArgs[0]) && $callArgs[0] !== null) {
            $callArgs[0] = [$callArgs[0]];
        }

        if ($nargs === 1 && $params[0]->getType() && $params[0]->getType()->getName() === JList::class) {
            $jList = new JList($args);
            $callArgs = [$jList];
        }
        $res = $reflectionMethod->invokeArgs(null, $callArgs);
        if (is_numeric($res)) {
            $res = Utils::convertNumber($res);
        }

        return $res;
    }

    /**
     * Converts an ISO 8601 timestamp to milliseconds since the epoch
     */
    public static function dateTimeToMillis(?string $timestamp, ?string $picture): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        if ($picture === null) {
            if (self::isNumeric($timestamp)) {
                $dt = \DateTime::createFromFormat('Y', $timestamp, new \DateTimeZone('UTC'));
                return (($nullsafeVariable1 = $dt) ? $nullsafeVariable1->getTimestamp() : null) * 1000;
            }

            try {
                $len = strlen($timestamp);
                if ($len > 5 && ($timestamp[$len - 5] === '+' || $timestamp[$len - 5] === '-') && ctype_digit(substr($timestamp, -4))) {
                    $timestamp = substr($timestamp, 0, $len - 2) . ':' . substr($timestamp, $len - 2);
                }

                return (new \DateTimeImmutable($timestamp))->getTimestamp() * 1000;
            } catch (\Throwable $exception) {
                try {
                    $ldt = \DateTimeImmutable::createFromFormat('Y-m-d', $timestamp, new \DateTimeZone('UTC'));
                    return (($nullsafeVariable2 = $ldt) ? $nullsafeVariable2->setTime(0, 0)->getTimestamp() : null) * 1000;
                } catch (\Throwable $exception2) {
                    return (new \DateTimeImmutable($timestamp, new \DateTimeZone(date_default_timezone_get())))->getTimestamp() * 1000;
                }
            }
        } else {
            return DateTimeUtils::parseDateTime($timestamp, $picture);
        }
    }

    public static function isNumeric(?string $cs): bool
    {
        if ($cs === null || $cs === '') {
            return false;
        }

        return ctype_digit($cs);
    }

    /**
     * @param int|float $millis
     */
    public static function dateTimeFromMillis($millis, ?string $picture, ?string $timezone): ?string
    {
        return DateTimeUtils::formatDateTime((int) $millis, $picture, $timezone);
    }

    /**
     * @param int|float|null $value
     */
    public static function formatInteger($value, string $picture): ?string
    {
        if ($value === null) {
            return null;
        }

        return DateTimeUtils::formatInteger((int) $value, $picture);
    }

    /**
     * @return float|int|null
     */
    public static function parseInteger(string $value, ?string $picture)
    {
        if ($value === null) {
            return null;
        }

        if ($picture !== null) {
            if ($picture === "#") {
                throw new \Exception("Formatting or parsing an integer with '#' not supported");
            }

            if (substr_compare($picture, ";o", -strlen(";o")) === 0) {
                $picture = substr($picture, 0, -2);
            }

            if ($picture === "a") {
                return DateTimeUtils::lettersToDecimal($value, 'a');
            }

            if ($picture === "A") {
                return DateTimeUtils::lettersToDecimal($value, 'A');
            }

            if ($picture === "i") {
                return DateTimeUtils::romanToDecimal(strtoupper($value));
            }

            if ($picture === "I") {
                return DateTimeUtils::romanToDecimal($value);
            }

            if ($picture === "w") {
                return DateTimeUtils::wordsToLong($value);
            }

            if (in_array($picture, ["W", "wW", "Ww"], true)) {
                return DateTimeUtils::wordsToLong(strtolower($value));
            }

            if (strpos($picture, ":") !== false) {
                $value = str_replace(":", ",", $value);
                $picture = str_replace(":", ",", $picture);
            }
        }

        try {
            $numberFormatter = new \NumberFormatter("en_US", \NumberFormatter::DECIMAL);
            return $numberFormatter->parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @param mixed $arg
     * @return mixed
     */
    public static function functionClone($arg)
    {
        if ($arg === null) {
            return null;
        }
        return json_decode((string) self::string($arg, false), true);
    }

}
