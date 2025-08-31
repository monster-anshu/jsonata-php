<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

use RuntimeException;

class DateTimeUtils
{
    private const FEW = [
        "Zero",
        "One",
        "Two",
        "Three",
        "Four",
        "Five",
        "Six",
        "Seven",
        "Eight",
        "Nine",
        "Ten",
        "Eleven",
        "Twelve",
        "Thirteen",
        "Fourteen",
        "Fifteen",
        "Sixteen",
        "Seventeen",
        "Eighteen",
        "Nineteen"
    ];

    private const ORDINALS = [
        "Zeroth",
        "First",
        "Second",
        "Third",
        "Fourth",
        "Fifth",
        "Sixth",
        "Seventh",
        "Eighth",
        "Ninth",
        "Tenth",
        "Eleventh",
        "Twelfth",
        "Thirteenth",
        "Fourteenth",
        "Fifteenth",
        "Sixteenth",
        "Seventeenth",
        "Eighteenth",
        "Nineteenth"
    ];

    private const DECADES = [
        "Twenty",
        "Thirty",
        "Forty",
        "Fifty",
        "Sixty",
        "Seventy",
        "Eighty",
        "Ninety"
    ];

    private const MAGNITUDES = [
        "Thousand",
        "Million",
        "Billion",
        "Trillion"
    ];

    private static array $wordValues = [];

    private static array $wordValuesLong = [];

    private static array $romanNumerals = [];

    private static array $romanValues = [];

    private static array $suffix123 = [];

    private static array $decimalGroups = [
        0x30,
        0x0660,
        0x06F0,
        0x07C0,
        0x0966,
        0x09E6,
        0x0A66,
        0x0AE6,
        0x0B66,
        0x0BE6,
        0x0C66,
        0x0CE6,
        0x0D66,
        0x0DE6,
        0x0E50,
        0x0ED0,
        0x0F20,
        0x1040,
        0x1090,
        0x17E0,
        0x1810,
        0x1946,
        0x19D0,
        0x1A80,
        0x1A90,
        0x1B50,
        0x1BB0,
        0x1C40,
        0x1C50,
        0xA620,
        0xA8D0,
        0xA900,
        0xA9D0,
        0xA9F0,
        0xAA50,
        0xABF0,
        0xFF10
    ];

    private static array $defaultPresentationModifiers = [];

    private static array $days = [
        "",
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday"
    ];

    private static array $months = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December"
    ];

    private static ?object $iso8601Spec = null;

    private static bool $initialized = false;

    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$wordValues = self::createWordValues(false);
        self::$wordValuesLong = self::createWordValues(true);
        self::$romanValues = self::createRomanValues();
        self::$suffix123 = self::createSuffixMap();
        self::$defaultPresentationModifiers = self::createDefaultPresentationModifiers();
        self::$romanNumerals = self::createRomanNumerals();

        self::$initialized = true;
    }

    private static function createWordValues(bool $isLong): array
    {
        $wordValues = [];
        $few = self::FEW;
        $ordinals = self::ORDINALS;
        $decades = self::DECADES;
        $magnitudes = self::MAGNITUDES;

        foreach ($few as $i => $word) {
            $wordValues[strtolower($word)] = $isLong ? (int) $i : (int) $i;
        }

        foreach ($ordinals as $i => $word) {
            $wordValues[strtolower($word)] = $isLong ? (int) $i : (int) $i;
        }

        foreach ($decades as $i => $word) {
            $lword = strtolower($word);
            $val = ($i + 2) * 10;
            $wordValues[$lword] = $isLong ? (int) $val : (int) $val;
            $wordValues[substr($lword, 0, -1) . "ieth"] = $isLong ? (int) $val : (int) $val;
        }

        $wordValues["hundredth"] = $isLong ? 100 : 100;
        $wordValues["hundreth"] = $isLong ? 100 : 100;

        foreach ($magnitudes as $i => $word) {
            $lword = strtolower($word);
            $val = 10 ** (($i + 1) * 3);
            $wordValues[$lword] = $isLong ? (int) $val : (int) $val;
            $wordValues[$lword . "th"] = $isLong ? (int) $val : (int) $val;
        }

        return $wordValues;
    }

    private static function createRomanValues(): array
    {
        return [
            "M" => 1000,
            "D" => 500,
            "C" => 100,
            "L" => 50,
            "X" => 10,
            "V" => 5,
            "I" => 1,
        ];
    }

    private static function createRomanNumerals(): array
    {
        return [
            ['value' => 1000, 'letters' => "m"],
            ['value' => 900, 'letters' => "cm"],
            ['value' => 500, 'letters' => "d"],
            ['value' => 400, 'letters' => "cd"],
            ['value' => 100, 'letters' => "c"],
            ['value' => 90, 'letters' => "xc"],
            ['value' => 50, 'letters' => "l"],
            ['value' => 40, 'letters' => "xl"],
            ['value' => 10, 'letters' => "x"],
            ['value' => 9, 'letters' => "ix"],
            ['value' => 5, 'letters' => "v"],
            ['value' => 4, 'letters' => "iv"],
            ['value' => 1, 'letters' => "i"]
        ];
    }

    private static function createSuffixMap(): array
    {
        return ["1" => "st", "2" => "nd", "3" => "rd"];
    }

    private static function createDefaultPresentationModifiers(): array
    {
        return [
            'Y' => "1",
            'M' => "1",
            'D' => "1",
            'd' => "1",
            'F' => "n",
            'W' => "1",
            'w' => "1",
            'X' => "1",
            'x' => "1",
            'H' => "1",
            'h' => "1",
            'P' => "n",
            'm' => "01",
            's' => "01",
            'f' => "1",
            'Z' => "01:01",
            'z' => "01:01",
            'C' => "n",
            'E' => "n"
        ];
    }

    public static function numberToWords(int|string $value, bool $ordinal): string
    {
        self::initialize();
        return self::lookup((int) $value, false, $ordinal);
    }

    private static function lookup(int $num, bool $prev, bool $ord): string
    {
        $words = "";
        $few = self::FEW;
        $ordinals = self::ORDINALS;
        $decades = self::DECADES;
        $magnitudes = self::MAGNITUDES;

        if ($num <= 19) {
            $words = ($prev ? " and " : "") . ($ord ? $ordinals[$num] : $few[$num]);
        } elseif ($num < 100) {
            $tens = (int) ($num / 10);
            $remainder = $num % 10;
            $words = ($prev ? " and " : "") . $decades[$tens - 2];
            if ($remainder > 0) {
                $words .= "-" . self::lookup($remainder, false, $ord);
            } elseif ($ord) {
                $words = substr($words, 0, -1) . "ieth";
            }
        } elseif ($num < 1000) {
            $hundreds = (int) ($num / 100);
            $remainder = $num % 100;
            $words = ($prev ? ", " : "") . $few[$hundreds] . " Hundred";
            if ($remainder > 0) {
                $words .= self::lookup($remainder, true, $ord);
            } elseif ($ord) {
                $words .= "th";
            }
        } else {
            $mag = (int) floor(log10($num) / 3);
            if ($mag > count($magnitudes)) {
                $mag = count($magnitudes);
            }

            $factor = 10 ** ($mag * 3);
            $mant = (int) floor($num / $factor);
            $remainder = $num - $mant * $factor;
            $words = ($prev ? ", " : "") . self::lookup($mant, false, false) . " " . $magnitudes[$mag - 1];
            if ($remainder > 0) {
                $words .= self::lookup($remainder, true, $ord);
            } elseif ($ord) {
                $words .= "th";
            }
        }

        return $words;
    }



    public static function wordsToLong(string $text): int
    {
        self::initialize();
        $parts = preg_split("/,\\s|\\sand\\s|[\\s\\-]/", strtolower($text));
        $values = array_map(fn ($part) => self::$wordValuesLong[$part] ?? null, $parts);
        $segs = [0];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if ($value < 100) {
                $top = array_pop($segs);
                if ($top >= 1000) {
                    $segs[] = $top;
                    $top = 0;
                }

                $segs[] = $top + $value;
            } else {
                $segs[] = array_pop($segs) * $value;
            }
        }

        return array_sum($segs);
    }

    private static function decimalToRoman(int $value): string
    {
        $roman = '';
        foreach (self::$romanNumerals as $romanNumeral) {
            while ($value >= $romanNumeral['value']) {
                $roman .= $romanNumeral['letters'];
                $value -= $romanNumeral['value'];
            }
        }

        return $roman;
    }

    public static function romanToDecimal(string $roman): int
    {
        $decimal = 0;
        $max = 1;
        $len = strlen($roman);
        for ($i = $len - 1; $i >= 0; --$i) {
            $digit = $roman[$i];
            $value = self::$romanValues[$digit] ?? 0;
            if ($value < $max) {
                $decimal -= $value;
            } else {
                $max = $value;
                $decimal += $value;
            }
        }

        return $decimal;
    }

    private static function decimalToLetters(int $value, string $aChar): string
    {
        $letters = [];
        $aCode = ord($aChar);
        while ($value > 0) {
            array_unshift($letters, chr(($value - 1) % 26 + $aCode));
            $value = (int) (($value - 1) / 26);
        }

        return implode('', $letters);
    }

    public static function formatInteger(int|string $value, string $picture): string
    {
        self::initialize();
        $format = self::analyseIntegerPicture($picture);
        return self::formatIntegerInternal((int) $value, $format);
    }

    private static function formatIntegerInternal(int $value, object $format): string
    {
        $formattedInteger = "";
        $negative = $value < 0;
        $value = abs($value);

        switch ($format->primary) {
            case 'letters':
                $char = $format->case_type === 'upper' ? "A" : "a";
                $formattedInteger = self::decimalToLetters($value, $char);
                break;
            case 'roman':
                $formattedInteger = self::decimalToRoman($value);
                if ($format->case_type === 'upper') {
                    $formattedInteger = strtoupper($formattedInteger);
                }

                break;
            case 'words':
                $formattedInteger = self::numberToWords($value, $format->ordinal);
                if ($format->case_type === 'upper') {
                    $formattedInteger = strtoupper($formattedInteger);
                } elseif ($format->case_type === 'lower') {
                    $formattedInteger = strtolower($formattedInteger);
                }

                break;
            case 'decimal':
                $formattedInteger = (string) $value;
                $padLength = $format->mandatoryDigits - strlen($formattedInteger);
                if ($padLength > 0) {
                    $formattedInteger = str_pad($formattedInteger, $format->mandatoryDigits, "0", STR_PAD_LEFT);
                }

                if ($format->zeroCode !== 0x30) {
                    $chars = str_split($formattedInteger);
                    foreach ($chars as &$char) {
                        $char = chr(ord($char) + $format->zeroCode - 0x30);
                    }

                    $formattedInteger = implode('', $chars);
                }

                if ($format->regular) {
                    $sep = $format->groupingSeparators[0];
                    $n = (strlen($formattedInteger) - 1) / $sep->position;
                    for ($i = (int) $n; $i > 0; --$i) {
                        $pos = strlen($formattedInteger) - $i * $sep->position;
                        $formattedInteger = substr_replace($formattedInteger, $sep->character, $pos, 0);
                    }
                } else {
                    $separators = $format->groupingSeparators;
                    $len = strlen($formattedInteger);
                    for ($i = count($separators) - 1; $i >= 0; --$i) {
                        $sep = $separators[$i];
                        $pos = $len - $sep->position;
                        $formattedInteger = substr_replace($formattedInteger, $sep->character, $pos, 0);
                    }
                }

                if ($format->ordinal) {
                    $lastDigit = substr($formattedInteger, -1);
                    $suffix = self::$suffix123[$lastDigit] ?? null;
                    if ($suffix === null || (strlen($formattedInteger) > 1 && $formattedInteger[strlen($formattedInteger) - 2] === '1')) {
                        $suffix = "th";
                    }

                    $formattedInteger .= $suffix;
                }

                break;
            case 'sequence':
                throw new RuntimeException('Sequence formatting not supported for token: ' . $format->token);
        }

        if ($negative) {
            $formattedInteger = "-" . $formattedInteger;
        }

        return $formattedInteger;
    }

    private static function analyseIntegerPicture(string $picture): object
    {
        $format = (object) [
            'type' => 'integer',
            'primary' => 'decimal',
            'case_type' => 'lower',
            'ordinal' => false,
            'zeroCode' => 0,
            'mandatoryDigits' => 0,
            'optionalDigits' => 0,
            'regular' => false,
            'groupingSeparators' => [],
            'token' => ''
        ];

        $semicolon = strrpos($picture, ";");
        if ($semicolon === false) {
            $primaryFormat = $picture;
        } else {
            $primaryFormat = substr($picture, 0, $semicolon);
            $formatModifier = substr($picture, $semicolon + 1);
            if ($formatModifier[0] === 'o') {
                $format->ordinal = true;
            }
        }

        switch ($primaryFormat) {
            case "A":
                $format->case_type = 'upper';
                // no break
            case "a":
                $format->primary = 'letters';
                break;
            case "I":
                $format->case_type = 'upper';
                // no break
            case "i":
                $format->primary = 'roman';
                break;
            case "W":
                $format->case_type = 'upper';
                $format->primary = 'words';
                break;
            case "Ww":
                $format->case_type = 'title';
                $format->primary = 'words';
                break;
            case "w":
                $format->primary = 'words';
                break;
            default:
                $zeroCode = null;
                $mandatoryDigits = 0;
                $optionalDigits = 0;
                $groupingSeparators = [];
                $separatorPosition = 0;
                $formatCodepoints = array_reverse(str_split($primaryFormat));

                foreach ($formatCodepoints as $formatCodepoint) {
                    $digit = false;
                    $code = ord($formatCodepoint);
                    foreach (self::$decimalGroups as $decimalGroup) {
                        if ($code >= $decimalGroup && $code <= $decimalGroup + 9) {
                            $digit = true;
                            ++$mandatoryDigits;
                            ++$separatorPosition;
                            if ($zeroCode === null) {
                                $zeroCode = $decimalGroup;
                            } elseif ($decimalGroup !== $zeroCode) {
                                throw new RuntimeException("Different decimal groups found in picture string");
                            }

                            break;
                        }
                    }

                    if (!$digit) {
                        if ($code === 0x23) { // '#'
                            ++$separatorPosition;
                            ++$optionalDigits;
                        } else {
                            $groupingSeparators[] = (object) ['position' => $separatorPosition, 'character' => $formatCodepoint];
                        }
                    }
                }

                if ($mandatoryDigits > 0) {
                    $format->primary = 'decimal';
                    $format->zeroCode = $zeroCode;
                    $format->mandatoryDigits = $mandatoryDigits;
                    $format->optionalDigits = $optionalDigits;

                    $regular = self::getRegularRepeat($groupingSeparators);
                    if ($regular > 0) {
                        $format->regular = true;
                        $format->groupingSeparators[] = (object) ['position' => $regular, 'character' => $groupingSeparators[0]->character];
                    } else {
                        $format->regular = false;
                        $format->groupingSeparators = $groupingSeparators;
                    }
                } else {
                    $format->primary = 'sequence';
                    $format->token = $primaryFormat;
                }
        }

        return $format;
    }

    private static function getRegularRepeat(array $separators): int
    {
        if ($separators === []) {
            return 0;
        }

        $sepChar = $separators[0]->character;
        foreach ($separators as $sep) {
            if ($sep->character !== $sepChar) {
                return 0;
            }
        }

        $indexes = array_map(fn ($sep) => $sep->position, $separators);

        $gcd = function ($a, $b) use (&$gcd) {
            return $b === 0 ? $a : $gcd($b, $a % $b);
        };

        $factor = array_reduce($indexes, fn ($carry, $item) => $gcd($carry, $item), $indexes[0]);
        $counter = count($indexes);

        for ($i = 1; $i <= $counter; ++$i) {
            if (!in_array($i * $factor, $indexes)) {
                return 0;
            }
        }

        return $factor;
    }

    private static function analyseDateTimePicture(string $picture): object
    {
        $format = (object) ['type' => 'datetime', 'parts' => []];
        $start = 0;
        $pos = 0;

        while ($pos < strlen($picture)) {
            if ($picture[$pos] === '[') {
                if (($picture[$pos + 1] ?? null) === '[') {
                    self::addLiteral($format, $picture, $start, $pos);
                    $format->parts[] = (object) ['type' => 'literal', 'value' => '['];
                    $pos += 2;
                    $start = $pos;
                    continue;
                }

                self::addLiteral($format, $picture, $start, $pos);
                $start = $pos;
                $pos = strpos($picture, "]", $start);

                if ($pos === false) {
                    throw new RuntimeException("Missing closing bracket in picture string");
                }

                $marker = str_replace(' ', '', substr($picture, $start + 1, $pos - $start - 1));
                $def = (object) ['type' => 'marker', 'component' => $marker[0]];

                $comma = strrpos($marker, ",");
                $presMod = '';

                if ($comma !== false) {
                    $widthMod = substr($marker, $comma + 1);
                    $dash = strpos($widthMod, "-");
                    $min = '';
                    $max = null;
                    if ($dash === false) {
                        $min = $widthMod;
                    } else {
                        $min = substr($widthMod, 0, $dash);
                        $max = substr($widthMod, $dash + 1);
                    }

                    $def->width = (object) ['min' => self::parseWidth($min), 'max' => self::parseWidth($max)];
                    $presMod = substr($marker, 1, $comma - 1);
                } else {
                    $presMod = substr($marker, 1);
                }

                if (strlen($presMod) === 1) {
                    $def->presentation1 = $presMod;
                } elseif (strlen($presMod) > 1) {
                    $lastChar = substr($presMod, -1);
                    if (str_contains("atco", $lastChar)) {
                        $def->presentation2 = $lastChar;
                        if ($lastChar === 'o') {
                            $def->ordinal = true;
                        }

                        $def->presentation1 = substr($presMod, 0, -1);
                    } else {
                        $def->presentation1 = $presMod;
                    }
                } else {
                    $def->presentation1 = self::$defaultPresentationModifiers[$def->component] ?? null;
                }

                if ($def->presentation1 === null) {
                    throw new RuntimeException('Unknown component specifier: ' . $def->component);
                }

                if ($def->presentation1[0] === 'n') {
                    $def->names = 'lower';
                } elseif ($def->presentation1[0] === 'N') {
                    $def->names = strlen($def->presentation1) > 1 && $def->presentation1[1] === 'n' ? 'title' : 'upper';
                } elseif (str_contains('YMDdFWwXxHhmsf', $def->component)) {
                    $integerPattern = $def->presentation1;
                    if (isset($def->presentation2)) {
                        $integerPattern .= ";" . $def->presentation2;
                    }

                    $def->integerFormat = self::analyseIntegerPicture($integerPattern);
                    $def->integerFormat->ordinal = $def->ordinal;
                    if (isset($def->width->min) && $def->integerFormat->mandatoryDigits < $def->width->min) {
                        $def->integerFormat->mandatoryDigits = $def->width->min;
                    }

                    if ($def->component === 'Y') {
                        $def->n = -1;
                        if (isset($def->width->max)) {
                            $def->n = $def->width->max;
                            $def->integerFormat->mandatoryDigits = $def->n;
                        } else {
                            $w = $def->integerFormat->mandatoryDigits + $def->integerFormat->optionalDigits;
                            if ($w >= 2) {
                                $def->n = $w;
                            }
                        }
                    }
                }

                if ($def->component === 'Z' || $def->component === 'z') {
                    $def->integerFormat = self::analyseIntegerPicture($def->presentation1);
                    $def->integerFormat->ordinal = $def->ordinal;
                }

                $format->parts[] = $def;
                $start = $pos + 1;
            }

            ++$pos;
        }

        self::addLiteral($format, $picture, $start, $pos);
        return $format;
    }

    private static function addLiteral(object $format, string $picture, int $start, int $end): void
    {
        if ($end > $start) {
            $literal = substr($picture, $start, $end - $start);
            $literal = $literal === ']]' ? ']' : str_replace(']]', ']', $literal);

            $format->parts[] = (object) ['type' => 'literal', 'value' => $literal];
        }
    }

    private static function parseWidth(?string $wm): ?int
    {
        if ($wm === null || $wm === "*") {
            return null;
        }

        return (int) $wm;
    }

    public static function formatDateTime(int $millis, ?string $picture = null, ?string $timezone = null): string
    {
        self::initialize();
        $offsetHours = 0;
        $offsetMinutes = 0;

        if ($timezone !== null) {
            $offset = (int) $timezone;
            $offsetHours = (int) ($offset / 100);
            $offsetMinutes = $offset % 100;
        }

        if ($picture === null) {
            if (self::$iso8601Spec === null) {
                self::$iso8601Spec = self::analyseDateTimePicture("[Y0001]-[M01]-[D01]T[H01]:[m01]:[s01].[f001][Z01:01t]");
            }

            $formatSpec = self::$iso8601Spec;
        } else {
            $formatSpec = self::analyseDateTimePicture($picture);
        }

        $offsetMillis = (60 * $offsetHours + $offsetMinutes) * 60 * 1000;
        $dateTime = new \DateTime('@' . (int) (($millis + $offsetMillis) / 1000), new \DateTimeZone('UTC'));

        $result = "";
        foreach ($formatSpec->parts as $part) {
            if ($part->type === 'literal') {
                $result .= $part->value;
            } else {
                $result .= self::formatComponent($dateTime, $part, $offsetHours, $offsetMinutes);
            }
        }

        return $result;
    }

    private static function formatComponent(\DateTime $date, object $markerSpec, int $offsetHours, int $offsetMinutes): string
    {
        $componentValue = self::getDateTimeFragment($date, $markerSpec->component);

        if (str_contains('YMDdFWwXxHhms', $markerSpec->component)) {
            if ($markerSpec->component === 'Y' && (isset($markerSpec->n) && $markerSpec->n !== -1)) {
                $componentValue = (string) ((int) $componentValue % 10 ** $markerSpec->n);
            }

            if (isset($markerSpec->names)) {
                if ($markerSpec->component === 'M' || $markerSpec->component === 'x') {
                    $componentValue = self::$months[(int) $componentValue - 1];
                } elseif ($markerSpec->component === 'F') {
                    $componentValue = self::$days[(int) $componentValue];
                } else {
                    throw new RuntimeException('Invalid name modifier for component ' . $markerSpec->component);
                }

                if ($markerSpec->names === 'upper') {
                    $componentValue = strtoupper((string) $componentValue);
                } elseif ($markerSpec->names === 'lower') {
                    $componentValue = strtolower((string) $componentValue);
                }

                if (isset($markerSpec->width->max) && strlen((string) $componentValue) > $markerSpec->width->max) {
                    $componentValue = substr((string) $componentValue, 0, $markerSpec->width->max);
                }
            } else {
                $componentValue = self::formatIntegerInternal((int) $componentValue, $markerSpec->integerFormat);
            }
        } elseif ($markerSpec->component === 'f') {
            $componentValue = self::formatIntegerInternal((int) $componentValue, $markerSpec->integerFormat);
        } elseif ($markerSpec->component === 'Z' || $markerSpec->component === 'z') {
            $offset = $offsetHours * 100 + $offsetMinutes;
            if ($markerSpec->integerFormat->regular) {
                $componentValue = self::formatIntegerInternal($offset, $markerSpec->integerFormat);
            } else {
                $numDigits = $markerSpec->integerFormat->mandatoryDigits;
                if ($numDigits === 1 || $numDigits === 2) {
                    $componentValue = self::formatIntegerInternal($offsetHours, $markerSpec->integerFormat);
                    if ($offsetMinutes !== 0) {
                        $componentValue .= ":" . self::formatIntegerInternal($offsetMinutes, self::analyseIntegerPicture("00"));
                    }
                } elseif ($numDigits === 3 || $numDigits === 4) {
                    $componentValue = self::formatIntegerInternal($offset, $markerSpec->integerFormat);
                } else {
                    throw new RuntimeException("Timezone format is invalid.");
                }
            }

            if ($offset >= 0) {
                $componentValue = "+" . $componentValue;
            }

            if ($markerSpec->component === 'z') {
                $componentValue = "GMT" . $componentValue;
            }

            if ($offset === 0 && isset($markerSpec->presentation2) && $markerSpec->presentation2 === 't') {
                $componentValue = "Z";
            }
        } elseif ($markerSpec->component === 'P') {
            if (isset($markerSpec->names) && $markerSpec->names === 'upper') {
                $componentValue = strtoupper($componentValue);
            }
        }

        return $componentValue;
    }

    private static function getDateTimeFragment(\DateTime $date, string $component): string
    {
        $componentValue = "";

        switch ($component) {
            case 'Y': // year
                $componentValue = $date->format('Y');
                break;
            case 'M': // month in year
                $componentValue = $date->format('n');
                break;
            case 'D': // day in month
                $componentValue = $date->format('j');
                break;
            case 'd': // day in year
                $componentValue = $date->format('z') + 1; // PHP's 'z' is 0-indexed, so add 1
                break;
            case 'F': // day of week
                $componentValue = $date->format('N'); // ISO-8601 numeric representation (1=Mon, 7=Sun)
                break;
            case 'W': // week in year (ISO 8601)
                $componentValue = $date->format('W');
                break;
            case 'w': // week in month (requires custom logic)
                $firstDayOfMonth = new \DateTime($date->format('Y-m-01'));
                $weekOfYear = (int) $date->format('W');
                $weekOfFirstDay = (int) $firstDayOfMonth->format('W');

                // Adjust for edge cases where the first week of the month might be in the previous year
                $componentValue = $weekOfYear - $weekOfFirstDay + 1;
                if ($componentValue <= 0) {
                    // The week of the first day of the month is in the previous year's week range
                    $componentValue += (int) (new \DateTime($firstDayOfMonth->format('Y') . '-12-28'))->format('W');
                }

                break;
            case 'H': // hour in day (24 hours)
                $componentValue = $date->format('H');
                break;
            case 'h': // hour in day (12 hours)
                $componentValue = $date->format('g');
                break;
            case 'P': // am/pm
                $componentValue = $date->format('a');
                break;
            case 'm': // minute
                $componentValue = $date->format('i');
                break;
            case 's': // second
                $componentValue = $date->format('s');
                break;
            case 'f': // millisecond
                // PHP's 'v' format gives milliseconds (or 'u' for microseconds)
                $componentValue = (int) ($date->format('v'));
                break;
            case 'Z': // timezone offset
                $componentValue = $date->format('O'); // e.g., +0500
                break;
            case 'z': // timezone
                $componentValue = $date->format('e'); // e.g., 'America/New_York'
                break;
            case 'C': // calendar (ISO)
            case 'E': // era (BC/AD)
                // PHP's standard date functions don't have a simple way to return "ISO" or a generic era string.
                // A direct equivalent would require custom logic.
                // For a simple return value, we can mirror the Java code.
                $componentValue = "ISO";
                break;
            default:
                $componentValue = "";
                break;
        }

        return (string) $componentValue;
    }

    public static function parseDateTime($timestamp, $picture)
    {
        $formatSpec = self::analyseDateTimePicture($picture);
        $matchSpec = self::generateRegex($formatSpec);

        $fullRegex = "/^";
        foreach ($matchSpec->parts as $part) {
            $fullRegex .= "(" . $part->regex . ")";
        }

        $fullRegex .= "$/i";

        if (preg_match($fullRegex, (string) $timestamp, $matches)) {
            $dmA = 161;
            $dmB = 130;
            $dmC = 84;
            $dmD = 72;
            $tmA = 23;
            $tmB = 47;

            $components = [];
            for ($i = 1; $i <= count($matches) - 1; ++$i) {
                $mpart = $matchSpec->parts[$i - 1];
                try {
                    $components[$mpart->component] = call_user_func($mpart->parser, $matches[$i]);
                } catch (UnsupportedOperationException) {
                    // do nothing
                }
            }

            if ($components === []) {
                return null;
            }

            $mask = 0;
            foreach (str_split("YXMxWwdD") as $part) {
                $mask <<= 1;
                if (isset($components[$part])) {
                    $mask += 1;
                }
            }

            $dateA = self::isType($dmA, $mask);
            $dateB = !$dateA && self::isType($dmB, $mask);
            $dateC = self::isType($dmC, $mask);
            $dateD = !$dateC && self::isType($dmD, $mask);

            $mask = 0;
            foreach (str_split("PHhmsf") as $part) {
                $mask <<= 1;
                if (isset($components[$part])) {
                    $mask += 1;
                }
            }

            $timeA = self::isType($tmA, $mask);
            $timeB = !$timeA && self::isType($tmB, $mask);

            $dateComps = $dateB ? "YB" : ($dateC ? "XxwF" : ($dateD ? "XWF" : "YMD"));
            $timeComps = $timeB ? "Phmsf" : "Hmsf";
            $comps = $dateComps . $timeComps;

            $now = new \DateTime("now", new \DateTimeZone("UTC"));

            $startSpecified = false;
            $endSpecified = false;
            foreach (str_split($comps) as $part) {
                if (!isset($components[$part])) {
                    if ($startSpecified) {
                        $components[$part] = (str_contains("MDd", $part)) ? 1 : 0;
                        $endSpecified = true;
                    } else {
                        $components[$part] = (int) self::getDateTimeFragment($now, $part);
                    }
                } else {
                    $startSpecified = true;
                    if ($endSpecified) {
                        throw new RuntimeException("Missing or unsupported format.");
                    }
                }
            }

            if (isset($components['M']) && $components['M'] > 0) {
                $components['M'] -= 1;
            } else {
                $components['M'] = 0;
            }

            if ($dateB) {
                $firstJan = new \DateTime($components['Y'] . '-01-01 00:00:00', new \DateTimeZone("UTC"));
                $firstJan->modify('+' . ($components['d'] - 1) . ' days');
                $components['M'] = (int) $firstJan->format('n') - 1;
                $components['D'] = (int) $firstJan->format('j');
            }

            if ($dateC || $dateD) {
                throw new RuntimeException("ISO week date formats not currently supported.");
            }

            if ($timeB) {
                $components['H'] = $components['h'] == 12 ? 0 : $components['h'];
                if (isset($components['P']) && $components['P'] == 1) {
                    $components['H'] += 12;
                }
            }

            $cal = new \DateTime('now', new \DateTimeZone("UTC"));
            $cal->setDate($components['Y'], $components['M'] + 1, $components['D']);
            $cal->setTime($components['H'], $components['m'], $components['s'], $components['f'] * 1000);

            $millis = $cal->getTimestamp() * 1000 + (int) ($cal->format('u') / 1000);

            if (isset($components['Z'])) {
                $millis -= $components['Z'] * 60 * 1000;
            } elseif (isset($components['z'])) {
                $millis -= $components['z'] * 60 * 1000;
            }

            return $millis;
        }

        return null;
    }

    private static function generateRegex($formatSpec)
    {
        $matcher = (object) ['parts' => []];
        foreach ($formatSpec->parts as $part) {
            $res = null;
            if ($part->type == "literal") {
                $regex = preg_quote((string) $part->value, '/');
                $res = (object) [
                    'regex' => $regex,
                    'component' => $part->component,
                    'parser' => function ($value): void {
                        throw new UnsupportedOperationException();
                    }
                ];
            } elseif ($part->component == 'Z' || $part->component == 'z') {
                $separator = isset($part->integerFormat->groupingSeparators[0]) && count($part->integerFormat->groupingSeparators) == 1 && $part->integerFormat->regular;
                $regex = "";
                if ($part->component == 'z') {
                    $regex = "GMT";
                }

                $regex .= "[-+][0-9]+";
                if ($separator) {
                    $regex .= preg_quote((string) $part->integerFormat->groupingSeparators[0]->character, '/') . "[0-9]+";
                }

                $res = (object) [
                    'regex' => $regex,
                    'component' => $part->component,
                    'parser' => function ($value) use ($part, $separator) {
                        if ($part->component == 'z') {
                            $value = substr($value, 3);
                        }

                        $offsetHours = 0;
                        $offsetMinutes = 0;
                        if ($separator) {
                            $offsetHours = (int) substr($value, 0, strpos($value, (string) $part->integerFormat->groupingSeparators[0]->character));
                            $offsetMinutes = (int) substr($value, strpos($value, (string) $part->integerFormat->groupingSeparators[0]->character) + 1);
                        } else {
                            $numdigits = strlen($value) - 1;
                            if ($numdigits <= 2) {
                                $offsetHours = (int) $value;
                            } else {
                                $offsetHours = (int) substr($value, 0, 3);
                                $offsetMinutes = (int) substr($value, 3);
                            }
                        }

                        return $offsetHours * 60 + $offsetMinutes;
                    }
                ];
            } elseif ($part->integerFormat != null) {
                $res = self::generateIntegerRegex($part->component, $part->integerFormat);
            } else {
                $regex = "[a-zA-Z]+";
                $lookup = [];
                if ($part->component == 'M' || $part->component == 'x') {
                    $counter = count(self::$months);
                    for ($i = 0; $i < $counter; ++$i) {
                        if (isset($part->width->right)) {
                            $lookup[substr((string) self::$months[$i], 0, $part->width->right)] = $i + 1;
                        } else {
                            $lookup[self::$months[$i]] = $i + 1;
                        }
                    }
                } elseif ($part->component == 'F') {
                    $counter = count(self::$days);
                    for ($i = 1; $i < $counter; ++$i) {
                        if (isset($part->width->right)) {
                            $lookup[substr((string) self::$days[$i], 0, $part->width->right)] = $i;
                        } else {
                            $lookup[self::$days[$i]] = $i;
                        }
                    }
                } elseif ($part->component == 'P') {
                    $lookup["am"] = 0;
                    $lookup["AM"] = 0;
                    $lookup["pm"] = 1;
                    $lookup["PM"] = 1;
                } else {
                    throw new RuntimeException(sprintf("Invalid name modifier for component '%s'.", $part->component));
                }

                $res = (object) [
                    'regex' => $regex,
                    'component' => $part->component,
                    'parser' => fn ($value) => $lookup[$value]
                ];
            }

            $matcher->parts[] = $res;
        }

        return $matcher;
    }

    private static function generateIntegerRegex($component, $formatSpec)
    {
        $isUpper = $formatSpec->case_type == 'UPPER';
        switch ($formatSpec->primary) {
            case 'LETTERS':
                $regex = $isUpper ? "[A-Z]+" : "[a-z]+";
                $parser = (fn ($value) => self::lettersToDecimal($value, $isUpper ? 'A' : 'a'));
                break;
            case 'ROMAN':
                $regex = $isUpper ? "[MDCLXVI]+" : "[mdclxvi]+";
                $parser = (fn ($value) => self::romanToDecimal($isUpper ? $value : strtoupper((string) $value)));
                break;
            case 'WORDS':
                $words = array_keys(self::$wordValues);
                $words[] = "and";
                $words[] = "[\\-, ]";
                $regex = "(?:" . implode("|", array_map('preg_quote', $words)) . ")+";
                $parser = (fn ($value) => self::wordsToNumber(strtolower((string) $value)));
                break;
            case 'DECIMAL':
                $regex = "[0-9]+";
                switch ($component) {
                    case 'Y':
                        $regex = "[0-9]{2,4}";
                        break;
                    case 'M':
                    case 'D':
                    case 'H':
                    case 'h':
                    case 'm':
                    case 's':
                        $regex = "[0-9]{1,2}";
                        break;
                }

                if ($formatSpec->ordinal) {
                    $regex .= "(?:th|st|nd|rd)";
                }

                $parser = function ($value) use ($formatSpec) {
                    $digits = $value;
                    if ($formatSpec->ordinal) {
                        $digits = substr($value, 0, -2);
                    }

                    if ($formatSpec->regular) {
                        $digits = str_replace(",", "", $digits);
                    } else {
                        foreach ($formatSpec->groupingSeparators as $sep) {
                            $digits = str_replace($sep->character, "", $digits);
                        }
                    }

                    if ($formatSpec->zeroCode != 0x30) {
                        $chars = str_split($digits);
                        foreach ($chars as &$char) {
                            $char = chr(ord($char) - $formatSpec->zeroCode + 0x30);
                        }

                        $digits = implode("", $chars);
                    }

                    return (int) $digits;
                };
                break;
            case 'SEQUENCE':
            default:
                throw new RuntimeException("Sequence format unsupported.");
        }

        return (object) [
            'regex' => $regex,
            'component' => $component,
            'parser' => $parser
        ];
    }

    public static function lettersToDecimal($letters, $aChar)
    {
        $decimal = 0;
        $chars = str_split((string) $letters);
        $len = count($chars);
        for ($i = 0; $i < $len; ++$i) {
            $decimal += (ord($chars[$len - $i - 1]) - ord($aChar) + 1) * 26 ** $i;
        }

        return $decimal;
    }

    private static function isType($type, $mask)
    {
        return ((~$type & $mask) == 0) && ($type & $mask) != 0;
    }

    public static function wordsToNumber($words)
    {
        $words = str_replace(['-', ','], ' ', $words);
        $parts = explode(' ', $words);
        $total = 0;
        $current = 0;
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '0' || $part === 'and') {
                continue;
            }

            if (isset(self::$wordValues[$part])) {
                $value = self::$wordValues[$part];
                if ($value >= 1000) {
                    $total += $current * $value;
                    $current = 0;
                } elseif ($value >= 100) {
                    $current *= $value;
                } else {
                    $current += $value;
                }
            }
        }

        return $total + $current;
    }
}

// Mock helper classes and interfaces for demonstration
class UnsupportedOperationException extends \Exception
{
}

// Data structure mocks
class PictureFormat
{
}

class SpecPart
{
}

class Format
{
}

class GroupingSeparator
{
}

class Pair
{
}

class Constants
{
    public const ERR_MSG_MISSING_FORMAT = "Missing or unsupported format.";

    public const ERR_MSG_INVALID_NAME_MODIFIER = "Invalid name modifier for component '%s'.";

    public const ERR_MSG_SEQUENCE_UNSUPPORTED = "Sequence format unsupported.";
}
