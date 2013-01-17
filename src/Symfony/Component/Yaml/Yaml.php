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

/**
 * Yaml offers convenience methods to load and dump YAML.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Yaml
{
    /**
     * Be warned that PHP support will be removed in Symfony 2.3.
     *
     * @deprecated Deprecated since version 2.0, to be removed in 2.3.
     */
    public static $enablePhpParsing = false;

    /**
     * Enables PHP support when parsing YAML files.
     *
     * Be warned that PHP support will be removed in Symfony 2.3.
     *
     * @deprecated Deprecated since version 2.0, to be removed in 2.3.
     */
    public static function enablePhpParsing()
    {
        self::$enablePhpParsing = true;
    }

    /**
     * Sets the PHP support flag when parsing YAML files.
     *
     * Be warned that PHP support will be removed in Symfony 2.3.
     *
     * @param Boolean $boolean true if PHP parsing support is enabled, false otherwise
     *
     * @deprecated Deprecated since version 2.0, to be removed in 2.3.
     */
    public static function setPhpParsing($boolean)
    {
        self::$enablePhpParsing = (Boolean) $boolean;
    }

    /**
     * Checks if PHP support is enabled when parsing YAML files.
     *
     * Be warned that PHP support will be removed in Symfony 2.3.
     *
     * @return Boolean true if PHP parsing support is enabled, false otherwise
     *
     * @deprecated Deprecated since version 2.0, to be removed in 2.3.
     */
    public static function supportsPhpParsing()
    {
        return self::$enablePhpParsing;
    }

    /**
     * Parses YAML into a PHP array.
     *
     * The parse method, when supplied with a YAML stream (string or file),
     * will do its best to convert YAML in a file into a PHP array.
     *
     *  Usage:
     *  <code>
     *   $array = Yaml::parse('config.yml');
     *   print_r($array);
     *  </code>
     *
     * @param string $input Path to a YAML file or a string containing YAML
     *
     * @return array The YAML converted to a PHP array
     *
     * @throws ParseException If the YAML is not valid
     *
     * @api
     */
    public static function parse($input, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        // if input is a file, process it
        $file = '';
        if (strpos($input, "\n") === false && is_file($input)) {
            if (false === is_readable($input)) {
                throw new ParseException(sprintf('Unable to parse "%s" as the file is not readable.', $input));
            }

            $file = $input;
            if (self::$enablePhpParsing) {
                ob_start();
                $retval = include($file);
                $content = ob_get_clean();

                // if an array is returned by the config file assume it's in plain php form else in YAML
                $input = is_array($retval) ? $retval : $content;

                // if an array is returned by the config file assume it's in plain php form else in YAML
                if (is_array($input)) {
                    return $input;
                }
            } else {
                $input = file_get_contents($file);
            }
        }

        $yaml = new Parser();

        try {
            return $yaml->parse($input, $exceptionOnInvalidType, $objectSupport);
        } catch (ParseException $e) {
            if ($file) {
                $e->setParsedFile($file);
            }

            throw $e;
        }
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param array   $array                  PHP array
     * @param integer $inline                 The level where you switch to inline YAML
     * @param integer $indent                 The amount of spaces to use for indentation of nested nodes.
     * @param Boolean $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param Boolean $objectSupport          true if object support is enabled, false otherwise
     *
     * @return string A YAML string representing the original PHP array
     *
     * @api
     */
    public static function dump($array, $inline = 2, $indent = 4, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        $yaml = new Dumper();
        $yaml->setIndentation($indent);

        return $yaml->dump($array, $inline, $exceptionOnInvalidType, $objectSupport);
    }
}
