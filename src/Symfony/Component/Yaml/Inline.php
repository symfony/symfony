<?php

/*
 * This file is part of the Symfony package.
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

    /**
     * Converts a YAML string to a PHP array.
     *
     * @param string $value A YAML string
     *
     * @return array A PHP array representing the YAML string
     */
    static public function parse($value)
    {
        $value = trim($value);

        if (0 == strlen($value)) {
            return '';
        }

        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        switch ($value[0]) {
            case '[':
                $result = self::parseSequence($value);
                break;
            case '{':
                $result = self::parseMapping($value);
                break;
            default:
                $result = self::parseScalar($value);
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
     *
     * @return string The YAML string representing the PHP array
     *
     * @throws DumpException When trying to dump PHP resource
     */
    static public function dump($value)
    {
        switch (true) {
            case is_resource($value):
                throw new DumpException('Unable to dump PHP resources in a YAML file.');
            case is_object($value):
                return '!!php/object:'.serialize($value);
            case is_array($value):
                return self::dumpArray($value);
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case ctype_digit($value):
                return is_string($value) ? "'$value'" : (int) $value;
            case is_numeric($value):
                return is_infinite($value) ? str_ireplace('INF', '.Inf', strval($value)) : (is_string($value) ? "'$value'" : $value);
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
     * @param array $value The PHP array to dump
     *
     * @return string The YAML string representing the PHP array
     */
    static private function dumpArray($value)
    {
        // array
        $keys = array_keys($value);
        if ((1 == count($keys) && '0' == $keys[0])
            || (count($keys) > 1 && array_reduce($keys, function ($v, $w) { return (integer) $v + $w; }, 0) == count($keys) * (count($keys) - 1) / 2)
        ) {
            $output = array();
            foreach ($value as $val) {
                $output[] = self::dump($val);
            }

            return sprintf('[%s]', implode(', ', $output));
        }

        // mapping
        $output = array();
        foreach ($value as $key => $val) {
            $output[] = sprintf('%s: %s', self::dump($key), self::dump($val));
        }

        return sprintf('{ %s }', implode(', ', $output));
    }

    /**
     * Parses a scalar to a YAML string.
     *
     * @param scalar  $scalar
     * @param string  $delimiters
     * @param array   $stringDelimiters
     * @param integer &$i
     * @param Boolean $evaluate
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    static public function parseScalar($scalar, $delimiters = null, $stringDelimiters = array('"', "'"), &$i = 0, $evaluate = true)
    {
        if (in_array($scalar[$i], $stringDelimiters)) {
            // quoted scalar
            $output = self::parseQuotedScalar($scalar, $i);
        } else {
            // "normal" string
            if (!$delimiters) {
                $output = substr($scalar, $i);
                $i += strlen($output);

                // remove comments
                if (false !== $strpos = strpos($output, ' #')) {
                    $output = rtrim(substr($output, 0, $strpos));
                }
            } else if (preg_match('/^(.+?)('.implode('|', $delimiters).')/', substr($scalar, $i), $match)) {
                $output = $match[1];
                $i += strlen($output);
            } else {
                throw new ParseException(sprintf('Malformed inline YAML string (%s).', $scalar));
            }

            $output = $evaluate ? self::evaluateScalar($output) : $output;
        }

        return $output;
    }

    /**
     * Parses a quoted scalar to YAML.
     *
     * @param string  $scalar
     * @param integer $i
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    static private function parseQuotedScalar($scalar, &$i)
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
     * @param string  $sequence
     * @param integer $i
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    static private function parseSequence($sequence, &$i = 0)
    {
        $output = array();
        $len = strlen($sequence);
        $i += 1;

        // [foo, bar, ...]
        while ($i < $len) {
            switch ($sequence[$i]) {
                case '[':
                    // nested sequence
                    $output[] = self::parseSequence($sequence, $i);
                    break;
                case '{':
                    // nested mapping
                    $output[] = self::parseMapping($sequence, $i);
                    break;
                case ']':
                    return $output;
                case ',':
                case ' ':
                    break;
                default:
                    $isQuoted = in_array($sequence[$i], array('"', "'"));
                    $value = self::parseScalar($sequence, array(',', ']'), array('"', "'"), $i);

                    if (!$isQuoted && false !== strpos($value, ': ')) {
                        // embedded mapping?
                        try {
                            $value = self::parseMapping('{'.$value.'}');
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
     * @param string  $mapping
     * @param integer $i
     *
     * @return string A YAML string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    static private function parseMapping($mapping, &$i = 0)
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
                        $output[$key] = self::parseSequence($mapping, $i);
                        $done = true;
                        break;
                    case '{':
                        // nested mapping
                        $output[$key] = self::parseMapping($mapping, $i);
                        $done = true;
                        break;
                    case ':':
                    case ' ':
                        break;
                    default:
                        $output[$key] = self::parseScalar($mapping, array(',', '}'), array('"', "'"), $i);
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
     *
     * @return string A YAML string
     */
    static private function evaluateScalar($scalar)
    {
        $scalar = trim($scalar);

        switch (true) {
            case 'null' == strtolower($scalar):
            case '' == $scalar:
            case '~' == $scalar:
                return null;
            case 0 === strpos($scalar, '!str'):
                return (string) substr($scalar, 5);
            case 0 === strpos($scalar, '! '):
                return intval(self::parseScalar(substr($scalar, 2)));
            case 0 === strpos($scalar, '!!php/object:'):
                return unserialize(substr($scalar, 13));
            case ctype_digit($scalar):
                $raw = $scalar;
                $cast = intval($scalar);

                return '0' == $scalar[0] ? octdec($scalar) : (((string) $raw == (string) $cast) ? $cast : $raw);
            case 'true' === strtolower($scalar):
                return true;
            case 'false' === strtolower($scalar):
                return false;
            case is_numeric($scalar):
                return '0x' == $scalar[0].$scalar[1] ? hexdec($scalar) : floatval($scalar);
            case 0 == strcasecmp($scalar, '.inf'):
            case 0 == strcasecmp($scalar, '.NaN'):
                return -log(0);
            case 0 == strcasecmp($scalar, '-.inf'):
                return log(0);
            case preg_match('/^(-|\+)?[0-9,]+(\.[0-9]+)?$/', $scalar):
                return floatval(str_replace(',', '', $scalar));
            case preg_match(self::getTimestampRegex(), $scalar):
                return strtotime($scalar);
            default:
                return (string) $scalar;
        }
    }

    /**
     * Gets a regex that matches an unix timestamp
     *
     * @return string The regular expression
     */
    static private function getTimestampRegex()
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
