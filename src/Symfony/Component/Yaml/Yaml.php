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
 */
class Yaml
{
    /**
     * Parses YAML into a PHP value.
     *
     *  Usage:
     *  <code>
     *   $array = Yaml::parse(file_get_contents('config.yml'));
     *   print_r($array);
     *  </code>
     *
     * @param string $input                  A string containing YAML
     * @param bool   $exceptionOnInvalidType True if an exception must be thrown on invalid types false otherwise
     * @param bool   $objectSupport          True if object support is enabled, false otherwise
     * @param bool   $objectForMap           True if maps should return a stdClass instead of array()
     *
     * @return mixed The YAML converted to a PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public static function parse($input, $exceptionOnInvalidType = false, $objectSupport = false, $objectForMap = false)
    {
        $yaml = new Parser();

        return $yaml->parse($input, $exceptionOnInvalidType, $objectSupport, $objectForMap);
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param array $array                  PHP array
     * @param int   $inline                 The level where you switch to inline YAML
     * @param int   $indent                 The amount of spaces to use for indentation of nested nodes.
     * @param bool  $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool  $objectSupport          true if object support is enabled, false otherwise
     *
     * @return string A YAML string representing the original PHP array
     */
    public static function dump($array, $inline = 2, $indent = 4, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        if ($indent < 1) {
            throw new \InvalidArgumentException('The indentation must be greater than zero.');
        }

        $yaml = new Dumper();
        $yaml->setIndentation($indent);

        return $yaml->dump($array, $inline, 0, $exceptionOnInvalidType, $objectSupport);
    }
}
