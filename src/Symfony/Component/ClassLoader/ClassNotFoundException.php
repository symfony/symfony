<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader;

/**
 * A special exception that can be raised manually when a class is not found.
 *
 * When possible, this exception can be raised before using a class by checking
 * class_exists(). The purpose of this exception is to give some information
 * related to where the potential class should exist.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 */
class ClassNotFoundException extends \Exception
{
    public function __construct ($class, $code = null, $previous = null)
    {
        parent::__construct($this->generateMessage($class), $code, $previous);
    }

    /**
     * Attempts to generate as helpful of a message as possible.
     *
     * @param  $class The class name that could not be found
     * @return string
     */
    private function generateMessage($class)
    {
        // create an array of "potential paths" for this file
        $autoloaders = spl_autoload_functions();
        $paths = array();
        $universalAutoloaders = 0;
        $otherAutoloaders = 0;
        foreach ($autoloaders as $autoloader) {
            // check to see if the callable is an object:method callable
            if (is_array($autoloader) && is_object($autoloader[0])) {
                $obj = $autoloader[0];

                if ($obj instanceof UniversalClassLoader) {
                    $paths = array_merge($paths, $obj->getLoadTrace($class));
                    $universalAutoloaders++;
                } else {
                    $otherAutoloaders++;
                }
            }
        }

        return $this->constructMessage($class, $paths, $universalAutoloaders, $otherAutoloaders);
    }

    /**
     * Builds the actual message from all of the debug information
     *
     * @param string  $class The name of the class that cannot be found
     * @param array   $paths The array of paths looked in
     * @param integer $universalAutoloaders The number of universal autoloaders
     * @param integer $otherAutoloaders The number of other autoloaders
     *
     * @return string
     */
    private function constructMessage($class, $paths, $universalAutoloaders, $otherAutoloaders)
    {
        // no universal autoloaders means that we know nothing
        if (0 == $universalAutoloaders) {
            return sprintf('Class "%s" could not be autoloaded.', $class);
        }

        // there is a mixture of autoloaders, keep the message simple
        if ($otherAutoloaders > 0) {
            return sprintf('Class "%s" could not be autoloaded by the UniversalClassLoader or "%s" other autoloader(s).', $class, $otherAutoloaders);
        }

        // there are only universal autoloaders - return debug paths
        if (0 == count($paths)) {
            return sprintf('Class "%s" could not be autoloaded: No possible paths could be found for the class or namespace. Check the class name or your autoloader configuration.', $class);
        } elseif (1 == count($paths)) {
            $path = $paths[0];

            // the file doesn't exist at the one possible path
            if (!file_exists($path)) {
                return sprintf('Class "%s" could not be autoloaded at "%s" - that file does not exist. If this path is not correct, check your autoloader configuration.', $class, $path);
            }

            // the file exists, but the class is not in it
            return sprintf('Class "%s" could not be autoloaded: The file "%s" was included, but the class was not found. Check the class name and namespace in that file.', $class, $path);
        }

        // the class was looked for in multiple locations
        return sprintf('Class "%s" could not be autoloaded, but was searched for in the following locations: %s.', $class, implode(', ', $paths));
    }
}