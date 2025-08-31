<?php

declare(strict_types=1);

namespace Monster\JsonataPhp;

class Constants
{
    public const ERR_MSG_SEQUENCE_UNSUPPORTED = "Formatting or parsing an integer as a sequence starting with %s is not
supported by this implementation";

    public const ERR_MSG_DIFF_DECIMAL_GROUP = "In a decimal digit pattern, all digits must be from the same decimal group";

    public const ERR_MSG_NO_CLOSING_BRACKET = "No matching closing bracket ']' in date/time picture string";

    public const ERR_MSG_UNKNOWN_COMPONENT_SPECIFIER = "Unknown component specifier %s in date/time picture string";

    public const ERR_MSG_INVALID_NAME_MODIFIER = "The 'name' modifier can only be applied to months and days in the
date/time picture string, not %s";

    public const ERR_MSG_TIMEZONE_FORMAT = "The timezone integer format specifier cannot have more than four digits";

    public const ERR_MSG_MISSING_FORMAT = "The date/time picture string is missing specifiers required to parse the
timestamp";

    public const ERR_MSG_INVALID_OPTIONS_SINGLE_CHAR = "Argument 3 of function %s is invalid. The value of the %s property
must be a single character";

    public const ERR_MSG_INVALID_OPTIONS_STRING = "Argument 3 of function %s is invalid. The value of the %s property must
be a string";

    // Decimal format symbols
    public const SYMBOL_DECIMAL_SEPARATOR = "decimal-separator";

    public const SYMBOL_GROUPING_SEPARATOR = "grouping-separator";

    public const SYMBOL_INFINITY = "infinity";

    public const SYMBOL_MINUS_SIGN = "minus-sign";

    public const SYMBOL_NAN = "NaN";

    public const SYMBOL_PERCENT = "percent";

    public const SYMBOL_PER_MILLE = "per-mille";

    public const SYMBOL_ZERO_DIGIT = "zero-digit";

    public const SYMBOL_DIGIT = "digit";

    public const SYMBOL_PATTERN_SEPARATOR = "pattern-separator";
}
