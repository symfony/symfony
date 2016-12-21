<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml;

use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Util\StringReader;

/**
 * Inline implements a YAML parser/dumper for the YAML inline syntax.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Inline
{
    const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*(?:\'\'[^\']*)*)\')';

    public static $parsedLineNumber;

    private static $exceptionOnInvalidType = false;
    private static $objectSupport = false;
    private static $objectForMap = false;
    private static $constantSupport = false;

    /**
     * Converts a YAML string to a PHP value.
     *
     * @param string|StringReader $reader     A YAML string
     * @param int                 $flags      A bit field of PARSE_* constants to customize the YAML parser behavior
     * @param array               $references Mapping of variable names to values
     *
     * @return mixed A PHP value representing the YAML string
     *
     * @throws ParseException
     */
    public static function parse($reader, $flags = 0, $references = array())
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3 && !is_array($references)) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT flag instead.', E_USER_DEPRECATED);

            if ($references) {
                $flags |= Yaml::PARSE_OBJECT;
            }

            if (func_num_args() >= 4) {
                @trigger_error('Passing a boolean flag to toggle object for map support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT_FOR_MAP flag instead.', E_USER_DEPRECATED);

                if (func_get_arg(3)) {
                    $flags |= Yaml::PARSE_OBJECT_FOR_MAP;
                }
            }

            if (func_num_args() >= 5) {
                $references = func_get_arg(4);
            } else {
                $references = array();
            }
        }

        self::$exceptionOnInvalidType = (bool) (Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE & $flags);
        self::$objectSupport = (bool) (Yaml::PARSE_OBJECT & $flags);
        self::$objectForMap = (bool) (Yaml::PARSE_OBJECT_FOR_MAP & $flags);
        self::$constantSupport = (bool) (Yaml::PARSE_CONSTANT & $flags);

        if (2 /* MB_OVERLOAD_STRING */ & (int) ini_get('mbstring.func_overload')) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        if (!$reader instanceof StringReader) {
            $reader = new StringReader($reader);
        }
        $reader->consumeWhiteSpace();

        if ($reader->isFullyConsumed()) {
            return '';
        }

        $result = self::parseValue($reader, $flags, null, true, $references);

        // some comments are allowed at the end
        if (0 !== $reader->consumeWhiteSpace() && $reader->readChar('#')) {
            $reader->readCSpan("\n");
        }

        if (!$reader->isFullyConsumed()) {
            throw new ParseException(sprintf('Unexpected characters near "%s".', $reader->readToEnd()));
        }

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }

        return $result;
    }

    /**
     * Dumps a given PHP variable to a YAML string.
     *
     * @param mixed $value The PHP variable to convert
     * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     *
     * @return string The YAML string representing the PHP array
     *
     * @throws DumpException When trying to dump PHP resource
     */
    public static function dump($value, $flags = 0)
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::DUMP_OBJECT flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(2)) {
                $flags |= Yaml::DUMP_OBJECT;
            }
        }

        switch (true) {
            case is_resource($value):
                if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                    throw new DumpException(sprintf('Unable to dump PHP resources in a YAML file ("%s").', get_resource_type($value)));
                }

                return 'null';
            case $value instanceof \DateTimeInterface:
                return $value->format('c');
            case is_object($value):
                if (Yaml::DUMP_OBJECT & $flags) {
                    return '!php/object:'.serialize($value);
                }

                if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($value instanceof \stdClass || $value instanceof \ArrayObject)) {
                    return self::dumpArray((array) $value, $flags);
                }

                if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                    throw new DumpException('Object support when dumping a YAML file has been disabled.');
                }

                return 'null';
            case is_array($value):
                return self::dumpArray($value, $flags);
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case ctype_digit($value):
                return is_string($value) ? "'$value'" : (int) $value;
            case is_numeric($value):
                $locale = setlocale(LC_NUMERIC, 0);
                if (false !== $locale) {
                    setlocale(LC_NUMERIC, 'C');
                }
                if (is_float($value)) {
                    $repr = (string) $value;
                    if (is_infinite($value)) {
                        $repr = str_ireplace('INF', '.Inf', $repr);
                    } elseif (floor($value) == $value && $repr == $value) {
                        // Preserve float data type since storing a whole number will result in integer value.
                        $repr = '!!float '.$repr;
                    }
                } else {
                    $repr = is_string($value) ? "'$value'" : (string) $value;
                }
                if (false !== $locale) {
                    setlocale(LC_NUMERIC, $locale);
                }

                return $repr;
            case '' == $value:
                return "''";
            case self::isBinaryString($value):
                return '!!binary '.base64_encode($value);
            case Escaper::requiresDoubleQuoting($value):
                return Escaper::escapeWithDoubleQuotes($value);
            case Escaper::requiresSingleQuoting($value):
            case preg_match('{^[0-9]+[_0-9]*$}', $value):
            case preg_match(self::getHexRegex(), $value):
            case preg_match(self::getTimestampRegex(), $value):
                return Escaper::escapeWithSingleQuotes($value);
            default:
                return $value;
        }
    }

    /**
     * Check if given array is hash or just normal indexed array.
     *
     * @internal
     *
     * @param array $value The PHP array to check
     *
     * @return bool true if value is hash array, false otherwise
     */
    public static function isHash(array $value)
    {
        $expectedKey = 0;

        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * @param array $value The PHP array to dump
     * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     *
     * @return string The YAML string representing the PHP array
     */
    private static function dumpArray($value, $flags)
    {
        // array
        if ($value && !self::isHash($value)) {
            $output = array();
            foreach ($value as $val) {
                $output[] = self::dump($val, $flags);
            }

            return sprintf('[%s]', implode(', ', $output));
        }

        // hash
        $output = array();
        foreach ($value as $key => $val) {
            $output[] = sprintf('%s: %s', self::dump($key, $flags), self::dump($val, $flags));
        }

        return sprintf('{ %s }', implode(', ', $output));
    }

    private static function parseValue(StringReader $reader, &$flags = 0, $delimiters = null, $evaluate = true, &$references = array())
    {
        $reader->consumeWhiteSpace();

        // Reference
        if ($reader->readChar('*')) {
            $value = $reader->readCSpan(' #'.$delimiters);
            // an unquoted *
            if ('' === $value) {
                throw new ParseException('A reference must contain at least one character.');
            }
            if (!array_key_exists($value, $references)) {
                throw new ParseException(sprintf('Reference "%s" does not exist.', $value));
            }

            return $references[$value];
        }

        // Tagged value
        if ('!' === $reader->peek()) {
            if ($reader->readString('!str ')) {
                $reader->consumeWhiteSpace();
                $value = self::parseValueInner($reader, $flags, $delimiters, false, $references);
                if (!is_scalar($value)) {
                    throw new ParseException('Value of type "%s" can\'t be casted to a string.', gettype($value));
                }

                return (string) $value;
            }
            // Non-specific tag
            if ($reader->readString('! ')) {
                $value = self::parseValueInner($reader, $flags, $delimiters, $evaluate, $references);
                if (is_scalar($value)) {
                    // @todo deprecate the int conversion as it
                    // should be a string
                    // @see http://www.yaml.org/spec/1.2/spec.html#tag/non-specific/
                    return (int) $value;
                }

                return $value;
            }
            if ($tag = $reader->readAny(array('!php/object:', '!!php/object:'))) {
                $serializedObject = self::parseScalar($reader, $flags, $delimiters, $evaluate, $references);
                if (self::$objectSupport) {
                    if ('!!php/object:' === $tag) {
                        @trigger_error('The !!php/object tag to indicate dumped PHP objects is deprecated since version 3.1 and will be removed in 4.0. Use the !php/object tag instead.', E_USER_DEPRECATED);
                    }

                    return unserialize($serializedObject);
                }

                if (self::$exceptionOnInvalidType) {
                    throw new ParseException('Object support when parsing a YAML file has been disabled.');
                }

                return;
            }
            if ($reader->readString('!php/const:')) {
                $constant = self::parseScalar($reader, $flags, $delimiters, $evaluate, $references);
                if (self::$constantSupport) {
                    if (defined($constant)) {
                        return constant($constant);
                    }

                    throw new ParseException(sprintf('The constant "%s" is not defined.', $constant));
                }
                if (self::$exceptionOnInvalidType) {
                    throw new ParseException(sprintf('The string "!php/const:%s" could not be parsed as a constant. Have you forgotten to pass the "Yaml::PARSE_CONSTANT" flag to the parser?', $constant));
                }

                return;
            }
            if ($reader->readString('!!float ')) {
                $reader->consumeWhiteSpace();

                return (float) self::parseScalar($reader, $flags, $delimiters, $evaluate, $references);
            }
            if ($reader->readString('!!binary ')) {
                $reader->consumeWhiteSpace();

                return self::evaluateBinaryScalar(self::parseScalar($reader, $flags, $delimiters, $evaluate, $references));
            }

            // @todo deprecate using non-supported tags
        }

        return self::parseValueInner($reader, $flags, $delimiters, $evaluate, $references);
    }

    private static function parseValueInner(StringReader $reader, &$flags = 0, &$delimiters = null, $evaluate = true, &$references = array())
    {
        $reader->consumeWhiteSpace();

        if ($reader->readChar('[')) {
            return self::parseSequence($reader, $flags, $references);
        }
        if ($reader->readChar('{')) {
            return self::parseMapping($reader, $flags, $references);
        }

        return self::parseScalar($reader, $flags, $delimiters, $evaluate, $references);
    }

    /**
     * Parses a YAML scalar.
     *
     * @param StringReader $reader
     * @param int          &$flags
     * @param string       &$delimiters
     * @param bool         $evaluate
     * @param array        &$references
     *
     * @return string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     *
     * @internal
     */
    public static function parseScalar(StringReader $reader, &$flags = 0, &$delimiters = null, $evaluate = true, array &$references = array())
    {
        $unescaper = new Unescaper();
        if ($reader->readChar('"')) {
            return $unescaper->unescapeDoubleQuotedString($reader);
        }
        if ($reader->readChar('\'')) {
            return $unescaper->unescapeSingleQuotedString($reader);
        }

        // "normal" string
        if (null === $delimiters) {
            // remove comments
            $scalar = $reader->readCSpan("\n");
            if (preg_match('/[ \t]+#/', $scalar, $match, PREG_OFFSET_CAPTURE)) {
                $scalar = substr($scalar, 0, $match[0][1]);
            }
        } elseif ('' === $scalar = $reader->readCSpan($delimiters)) {
            throw new ParseException(sprintf('Malformed inline YAML string (%s).', $reader->readToEnd()));
        }

        // a non-quoted string cannot start with @ or ` (reserved) nor with a scalar indicator (| or >)
        if (1 === strspn($scalar, '@`|>', 0, 1)) {
            throw new ParseException(sprintf('The reserved indicator "%s" cannot start a plain scalar; you need to quote the scalar.', $scalar[0]));
        }

        // @todo deprecate any reserved indicators
        // @see http://www.yaml.org/spec/1.2/spec.html#c-indicator
        if (1 === strspn($scalar, '%', 0, 1)) {
            @trigger_error(sprintf('Not quoting the scalar "%s" starting with the "%s" indicator character is deprecated since Symfony 3.1 and will throw a ParseException in 4.0.', $scalar, $scalar[0]), E_USER_DEPRECATED);
        }
        $scalar = trim($scalar, "\t ");

        if ($evaluate) {
            return self::evaluateScalar($scalar, $flags);
        }

        return $scalar;
    }

    /**
     * Parses a YAML sequence.
     *
     * @param StringReader $reader
     * @param int          &$flags
     * @param array        &$references
     *
     * @return array
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseSequence(StringReader $reader, &$flags, array &$references = array())
    {
        $sequence = array();
        self::parseStructure($reader, ']', function () use ($reader, &$sequence, $flags, $references) {
            $isQuoted = in_array($reader->peek(), array('"', "'"));

            $value = self::parseValue($reader, $flags, '],', true, $references);

            if (is_string($value) && !$isQuoted && false !== strpos($value, ': ')) {
                try {
                    $value = self::parseMapping(new StringReader($value.'}'), $flags, $references);
                } catch (ParseException $e) {
                }
            }

            $sequence[] = $value;
        });

        return $sequence;
    }

    /**
     * Parses a YAML mapping.
     *
     * @param StringReader $reader
     * @param int          &$flags
     * @param array        &$references
     *
     * @return array|\stdClass
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseMapping(StringReader $reader, &$flags, &$references = array())
    {
        $mapping = array();
        self::parseStructure($reader, '}', function () use ($reader, &$mapping, $flags, $references) {
            $key = self::parseValue($reader, $flags, '},:', false, $references);
            // @todo deprecate using special values without
            // non-specific tag (false, true, null, ...)
            if (!is_string($key) && !is_int($key)) {
                throw new ParseException(sprintf('Mapping keys must be strings or integers, type "%s" provided.', gettype($key)));
            }
            if ($reader->readChar(':')) {
                if (!in_array($reader->peek(), array(' ', '[', ']', '{', '}'), true)) {
                    @trigger_error('Using a colon that is not followed by an indication character (i.e. " ", ",", "[", "]", "{", "}" is deprecated since version 3.2 and will throw a ParseException in 4.0.', E_USER_DEPRECATED);
                }

                $value = self::parseValue($reader, $flags, '},', true, $references);
            } else {
                $value = null;
            }

            if (isset($mapping[$key])) {
                @trigger_error(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated since version 3.2 and will throw \Symfony\Component\Yaml\Exception\ParseException in 4.0.', $key, self::$parsedLineNumber + 1), E_USER_DEPRECATED);
            } else {
                $mapping[$key] = $value;
            }
        });

        if (self::$objectForMap) {
            return (object) $mapping;
        }

        return $mapping;
    }

    private static function parseStructure(StringReader $reader, $closingMarker, callable $parseItem)
    {
        $reader->consumeWhiteSpace();
        // @todo deprecate support of consecutive comma
        while ($reader->readChar(',')) {
            $reader->consumeWhiteSpace();
        }

        while (!$reader->readChar($closingMarker)) {
            $parseItem();

            $reader->consumeWhiteSpace();
            if (!$reader->readChar(',')) {
                $reader->expectChar($closingMarker);
                break;
            }

            do {
                $reader->consumeWhiteSpace();

                // bc support of [ foo, , ]
            } while ($reader->readChar(','));
        }
    }

    /**
     * Evaluates scalars and replaces magic values.
     *
     * @param string $scalar
     * @param int    $flags
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException when object parsing support was disabled and the parser detected a PHP object or when a reference could not be resolved
     */
    private static function evaluateScalar($scalar, &$flags)
    {
        $scalar = rtrim($scalar);
        $scalarLower = strtolower($scalar);
        switch (true) {
            case 'null' === $scalarLower:
            case '~' === $scalar:
            case '' === $scalar:
                return;
            case 'true' === $scalarLower:
                return true;
            case 'false' === $scalarLower:
                return false;
            // Optimise for returning strings.
            case $scalar[0] === '+' || $scalar[0] === '-' || $scalar[0] === '.' || is_numeric($scalar[0]):
                switch (true) {
                    case preg_match('{^[+-]?[0-9][0-9_]*$}', $scalar):
                        $scalar = str_replace('_', '', (string) $scalar);
                        // omitting the break / return as integers are handled in the next case
                    case ctype_digit($scalar):
                        $raw = $scalar;
                        $cast = (int) $scalar;

                        return '0' == $scalar[0] ? octdec($scalar) : (((string) $raw == (string) $cast) ? $cast : $raw);
                    case '-' === $scalar[0] && ctype_digit(substr($scalar, 1)):
                        $raw = $scalar;
                        $cast = (int) $scalar;

                        return '0' == $scalar[1] ? octdec($scalar) : (((string) $raw === (string) $cast) ? $cast : $raw);
                    case is_numeric($scalar):
                    case preg_match(self::getHexRegex(), $scalar):
                        $scalar = str_replace('_', '', $scalar);

                        return '0x' === $scalar[0].$scalar[1] ? hexdec($scalar) : (float) $scalar;
                    case '.inf' === $scalarLower:
                    case '.nan' === $scalarLower:
                        return -log(0);
                    case '-.inf' === $scalarLower:
                        return log(0);
                    case preg_match('/^(-|\+)?[0-9][0-9,]*(\.[0-9_]+)?$/', $scalar):
                    case preg_match('/^(-|\+)?[0-9][0-9_]*(\.[0-9_]+)?$/', $scalar):
                        if (false !== strpos($scalar, ',')) {
                            @trigger_error('Using the comma as a group separator for floats is deprecated since version 3.2 and will be removed in 4.0.', E_USER_DEPRECATED);
                        }

                        return (float) str_replace(array(',', '_'), '', $scalar);
                    case preg_match(self::getTimestampRegex(), $scalar):
                        if (Yaml::PARSE_DATETIME & $flags) {
                            // When no timezone is provided in the parsed date, YAML spec says we must assume UTC.
                            return new \DateTime($scalar, new \DateTimeZone('UTC'));
                        }

                        $timeZone = date_default_timezone_get();
                        date_default_timezone_set('UTC');
                        $time = strtotime($scalar);
                        date_default_timezone_set($timeZone);

                        return $time;
                }
            default:
                return (string) $scalar;
        }
    }

    /**
     * @param string $scalar
     *
     * @return string
     *
     * @internal
     */
    public static function evaluateBinaryScalar($binaryData)
    {
        $parsedBinaryData = preg_replace('/\s/', '', $binaryData);

        if (0 !== (strlen($parsedBinaryData) % 4)) {
            throw new ParseException(sprintf('The normalized base64 encoded data (data without whitespace characters) length must be a multiple of four (%d bytes given).', strlen($parsedBinaryData)));
        }

        if (!preg_match('#^[A-Z0-9+/]+={0,2}$#i', $parsedBinaryData)) {
            throw new ParseException(sprintf('The base64 encoded data (%s) contains invalid characters.', $parsedBinaryData));
        }

        return base64_decode($parsedBinaryData, true);
    }

    private static function isBinaryString($value)
    {
        return !preg_match('//u', $value) || preg_match('/[^\x09-\x0d\x20-\xff]/', $value);
    }

    /**
     * Gets a regex that matches a YAML date.
     *
     * @return string The regular expression
     *
     * @see http://www.yaml.org/spec/1.2/spec.html#id2761573
     */
    private static function getTimestampRegex()
    {
        return <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
        -(?P<month>[0-9][0-9]?)
        -(?P<day>[0-9][0-9]?)
        (?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
        $~x
EOF;
    }

    /**
     * Gets a regex that matches a YAML number in hexadecimal notation.
     *
     * @return string
     */
    private static function getHexRegex()
    {
        return '~^0x[0-9a-f_]++$~i';
    }
}
