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

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Exception\DumpException;

/**
 * Inline implements a YAML parser/dumper for the YAML inline syntax.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Inline
{
    const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*(?:\'\'[^\']*)*)\')';

    private static $exceptionOnInvalidType = false;
    private static $objectSupport = false;
    private static $objectForMap = false;

    /**
     * Converts a YAML string to a PHP array.
     *
     * @param string $value                  A YAML string
     * @param bool   $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool   $objectSupport          true if object support is enabled, false otherwise
     * @param bool   $objectForMap           true if maps should return a stdClass instead of array()
     * @param array  $references             Mapping of variable names to values
     *
     * @return array A PHP array representing the YAML string
     *
     * @throws ParseException
     */
    public static function parse($value, $exceptionOnInvalidType = false, $objectSupport = false, $objectForMap = false, $references = array())
    {
        self::$exceptionOnInvalidType = $exceptionOnInvalidType;
        self::$objectSupport = $objectSupport;
        self::$objectForMap = $objectForMap;

        $value = trim($value);

        if (0 == strlen($value)) {
            return '';
        }

        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        $i = 0;
        switch ($value[0]) {
            case '[':
                $result = self::parseSequence($value, $i, $references);
                ++$i;
                break;
            case '{':
                $result = self::parseMapping($value, $i, $references);
                ++$i;
                break;
            default:
                $result = self::parseScalar($value, null, array('"', "'"), $i, true, $references);
        }

        // some comments are allowed at the end
        if (preg_replace('/\s+#.*$/A', '', substr($value, $i))) {
            throw new ParseException(sprintf('Unexpected characters near "%s".', substr($value, $i)));
        }

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }

        return $result;
    }

    /**
     * Dumps a given PHP variable to a YAML string.
     *
     * @param mixed   $value                  The PHP variable to convert
     * @param bool    $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool    $objectSupport          true if object support is enabled, false otherwise
     *
     * @return string The YAML string representing the PHP array
     *
     * @throws DumpException When trying to dump PHP resource
     */
    public static function dump($value, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        switch (true) {
            case is_resource($value):
                if ($exceptionOnInvalidType) {
                    throw new DumpException(sprintf('Unable to dump PHP resources in a YAML file ("%s").', get_resource_type($value)));
                }

                return 'null';
            case is_object($value):
                if ($objectSupport) {
                    return '!!php/object:'.serialize($value);
                }

                if ($exceptionOnInvalidType) {
                    throw new DumpException('Object support when dumping a YAML file has been disabled.');
                }

                return 'null';
            case is_array($value):
                return self::dumpArray($value, $exceptionOnInvalidType, $objectSupport);
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
                $repr = is_string($value) ? "'$value'" : (is_infinite($value) ? str_ireplace('INF', '.Inf', strval($value)) : strval($value));

                if (false !== $locale) {
                    setlocale(LC_NUMERIC, $locale);
                }

                return $repr;
            case Escaper::requiresDoubleQuoting($value):
                return Escaper::escapeWithDoubleQuotes($value);
            case Escaper::requiresSingleQuoting($value):
                return Escaper::escapeWithSingleQuotes($value);
            case '' == $value:
                return "''";
            case preg_match(self::getTimestampRegex(), $value):
            case in_array(strtolower($value), array('null', '~', 'true', 'false')):
                return "'$value'";
            default:
                return $value;
        }
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * @param array   $value                  The PHP array to dump
     * @param bool    $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool    $objectSupport          true if object support is enabled, false otherwise
     *
     * @return string The YAML string representing the PHP array
     */
    private static function dumpArray($value, $exceptionOnInvalidType, $objectSupport)
    {
        // array
        $keys = array_keys($value);
        if ((1 == count($keys) && '0' == $keys[0])
            || (count($keys) > 1 && array_reduce($keys, function ($v, $w) { return (int) $v + $w; }, 0) == count($keys) * (count($keys) - 1) / 2)
        ) {
            $output = array();
            foreach ($value as $val) {
                $output[] = self::dump($val, $exceptionOnInvalidType, $objectSupport);
            }

            return sprintf('[%s]', implode(', ', $output));
        }

        // mapping
        $output = array();
        foreach ($value as $key => $val) {
            $output[] = sprintf('%s: %s', self::dump($key, $exceptionOnInvalidType, $objectSupport), self::dump($val, $exceptionOnInvalidType, $objectSupport));
        }

        return sprintf('{ %s }', implode(', ', $output));
    }

    /**
     * Parses a scalar to a YAML string.
     *
     * @param scalar $scalar
     * @param string $delimiters
     * @param array  $stringDelimiters
     * @param int    &$i
     * @param bool   $evaluate
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    public static function parseScalar($scalar, $delimiters = null, $stringDelimiters = array('"', "'"), &$i = 0, $evaluate = true, $references = array())
    {
        if (in_array($scalar[$i], $stringDelimiters)) {
            // quoted scalar
            $output = self::parseQuotedScalar($scalar, $i);

            if (null !== $delimiters) {
                $tmp = ltrim(substr($scalar, $i), ' ');
                if (!in_array($tmp[0], $delimiters)) {
                    throw new ParseException(sprintf('Unexpected characters (%s).', substr($scalar, $i)));
                }
            }
        } else {
            // "normal" string
            if (!$delimiters) {
                $output = substr($scalar, $i);
                $i += strlen($output);

                // remove comments
                if (false !== $strpos = strpos($output, ' #')) {
                    $output = rtrim(substr($output, 0, $strpos));
                }
            } elseif (preg_match('/^(.+?)('.implode('|', $delimiters).')/', substr($scalar, $i), $match)) {
                $output = $match[1];
                $i += strlen($output);
            } else {
                throw new ParseException(sprintf('Malformed inline YAML string (%s).', $scalar));
            }

            if ($evaluate) {
                $output = self::evaluateScalar($output, $references);
            }
        }

        return $output;
    }

    /**
     * Parses a quoted scalar to YAML.
     *
     * @param string $scalar
     * @param int    &$i
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseQuotedScalar($scalar, &$i)
    {
        if (!preg_match('/'.self::REGEX_QUOTED_STRING.'/Au', substr($scalar, $i), $match)) {
            throw new ParseException(sprintf('Malformed inline YAML string (%s).', substr($scalar, $i)));
        }

        $output = substr($match[0], 1, strlen($match[0]) - 2);

        $unescaper = new Unescaper();
        if ('"' == $scalar[$i]) {
            $output = $unescaper->unescapeDoubleQuotedString($output);
        } else {
            $output = $unescaper->unescapeSingleQuotedString($output);
        }

        $i += strlen($match[0]);

        return $output;
    }

    /**
     * Parses a sequence to a YAML string.
     *
     * @param string $sequence
     * @param int    &$i
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseSequence($sequence, &$i = 0, $references = array())
    {
        $output = array();
        $len = strlen($sequence);
        $i += 1;

        // [foo, bar, ...]
        while ($i < $len) {
            switch ($sequence[$i]) {
                case '[':
                    // nested sequence
                    $output[] = self::parseSequence($sequence, $i, $references);
                    break;
                case '{':
                    // nested mapping
                    $output[] = self::parseMapping($sequence, $i, $references);
                    break;
                case ']':
                    return $output;
                case ',':
                case ' ':
                    break;
                default:
                    $isQuoted = in_array($sequence[$i], array('"', "'"));
                    $value = self::parseScalar($sequence, array(',', ']'), array('"', "'"), $i, true, $references);

                    // the value can be an array if a reference has been resolved to an array var
                    if (!is_array($value) && !$isQuoted && false !== strpos($value, ': ')) {
                        // embedded mapping?
                        try {
                            $pos = 0;
                            $value = self::parseMapping('{'.$value.'}', $pos, $references);
                        } catch (\InvalidArgumentException $e) {
                            // no, it's not
                        }
                    }

                    $output[] = $value;

                    --$i;
            }

            ++$i;
        }

        throw new ParseException(sprintf('Malformed inline YAML string %s', $sequence));
    }

    /**
     * Parses a mapping to a YAML string.
     *
     * @param string $mapping
     * @param int    &$i
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseMapping($mapping, &$i = 0, $references = array())
    {
        $output = array();
        $len = strlen($mapping);
        $i += 1;

        // {foo: bar, bar:foo, ...}
        while ($i < $len) {
            switch ($mapping[$i]) {
                case ' ':
                case ',':
                    ++$i;
                    continue 2;
                case '}':
                    if (self::$objectForMap) {
                        return (object) $output;
                    }

                    return $output;
            }

            // key
            $key = self::parseScalar($mapping, array(':', ' '), array('"', "'"), $i, false);

            // value
            $done = false;

            while ($i < $len) {
                switch ($mapping[$i]) {
                    case '[':
                        // nested sequence
                        $value = self::parseSequence($mapping, $i, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        }
                        $done = true;
                        break;
                    case '{':
                        // nested mapping
                        $value = self::parseMapping($mapping, $i, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        }
                        $done = true;
                        break;
                    case ':':
                    case ' ':
                        break;
                    default:
                        $value = self::parseScalar($mapping, array(',', '}'), array('"', "'"), $i, true, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        }
                        $done = true;
                        --$i;
                }

                ++$i;

                if ($done) {
                    continue 2;
                }
            }
        }

        throw new ParseException(sprintf('Malformed inline YAML string %s', $mapping));
    }

    /**
     * Evaluates scalars and replaces magic values.
     *
     * @param string $scalar
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException when object parsing support was disabled and the parser detected a PHP object
     */
    private static function evaluateScalar($scalar, $references = array())
    {
        $scalar = trim($scalar);
        $scalarLower = strtolower($scalar);

        if (0 === strpos($scalar, '*')) {
            if (false !== $pos = strpos($scalar, '#')) {
                $value = substr($scalar, 1, $pos - 2);
            } else {
                $value = substr($scalar, 1);
            }

            if (!array_key_exists($value, $references)) {
                throw new ParseException(sprintf('Reference "%s" does not exist.', $value));
            }

            return $references[$value];
        }

        switch (true) {
            case 'null' === $scalarLower:
            case '' === $scalar:
            case '~' === $scalar:
                return;
            case 'true' === $scalarLower:
                return true;
            case 'false' === $scalarLower:
                return false;
            // Optimise for returning strings.
            case $scalar[0] === '+' || $scalar[0] === '-' || $scalar[0] === '.' || $scalar[0] === '!' || is_numeric($scalar[0]):
                switch (true) {
                    case 0 === strpos($scalar, '!str'):
                        return (string) substr($scalar, 5);
                    case 0 === strpos($scalar, '! '):
                        return intval(self::parseScalar(substr($scalar, 2)));
                    case 0 === strpos($scalar, '!!php/object:'):
                        if (self::$objectSupport) {
                            return unserialize(substr($scalar, 13));
                        }

                        if (self::$exceptionOnInvalidType) {
                            throw new ParseException('Object support when parsing a YAML file has been disabled.');
                        }

                        return;
                    case ctype_digit($scalar):
                        $raw = $scalar;
                        $cast = intval($scalar);

                        return '0' == $scalar[0] ? octdec($scalar) : (((string) $raw == (string) $cast) ? $cast : $raw);
                    case '-' === $scalar[0] && ctype_digit(substr($scalar, 1)):
                        $raw = $scalar;
                        $cast = intval($scalar);

                        return '0' == $scalar[1] ? octdec($scalar) : (((string) $raw == (string) $cast) ? $cast : $raw);
                    case is_numeric($scalar):
                        return '0x' == $scalar[0].$scalar[1] ? hexdec($scalar) : floatval($scalar);
                    case '.inf' === $scalarLower:
                    case '.nan' === $scalarLower:
                        return -log(0);
                    case '-.inf' === $scalarLower:
                        return log(0);
                    case preg_match('/^(-|\+)?[0-9,]+(\.[0-9]+)?$/', $scalar):
                        return floatval(str_replace(',', '', $scalar));
                    case preg_match(self::getTimestampRegex(), $scalar):
                        return strtotime($scalar);
                }
            default:
                return (string) $scalar;
        }
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
}
