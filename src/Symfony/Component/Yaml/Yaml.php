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

/**
 * Yaml offers convenience methods to load and dump YAML.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Yaml
{
    static private $spec = '1.2';

    /**
     * Sets the YAML specification version to use.
     *
     * @param string $version The YAML specification version
     *
     * @throws \InvalidArgumentException When version of YAML specs is not supported
     */
    static public function setSpecVersion($version)
    {
        if (!in_array($version, array('1.1', '1.2'))) {
            throw new \InvalidArgumentException(sprintf('Version %s of the YAML specifications is not supported', $version));
        }

        self::$spec = $version;
    }

    /**
     * Gets the YAML specification version to use.
     *
     * @return string The YAML specification version
     */
    static public function getSpecVersion()
    {
        return self::$spec;
    }

    /**
     * Loads YAML into a PHP array.
     *
     * The load method, when supplied with a YAML stream (string or file),
     * will do its best to convert YAML in a file into a PHP array.
     *
     *  Usage:
     *  <code>
     *   $array = Yaml::load('config.yml');
     *   print_r($array);
     *  </code>
     *
     * @param string $input Path to a YAML file or a string containing YAML
     *
     * @return array The YAML converted to a PHP array
     *
     * @throws \InvalidArgumentException If the YAML is not valid
     *
     * @api
     */
    public static function load($input)
    {
        $file = '';

        // if input is a file, process it
        if (strpos($input, "\n") === false && is_file($input) && is_readable($input)) {
            $file = $input;

            ob_start();
            $retval = include($input);
            $content = ob_get_clean();

            // if an array is returned by the config file assume it's in plain php form else in YAML
            $input = is_array($retval) ? $retval : $content;
        }

        // if an array is returned by the config file assume it's in plain php form else in YAML
        if (is_array($input)) {
            return $input;
        }

        $yaml = new Parser();

        try {
            $ret = $yaml->parse($input);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to parse %s: %s', $file ? sprintf('file "%s"', $file) : 'string', $e->getMessage()), 0, $e);
        }

        return $ret;
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param array   $array PHP array
     * @param integer $inline The level where you switch to inline YAML
     *
     * @return string A YAML string representing the original PHP array
     *
     * @api
     */
    public static function dump($array, $inline = 2)
    {
        $yaml = new Dumper();

        return $yaml->dump($array, $inline);
    }
}
