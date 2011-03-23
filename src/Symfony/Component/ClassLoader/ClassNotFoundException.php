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

    private function generateMessage($class)
    {
        // create an array of "potential paths" for this file
        $autoloaders = spl_autoload_functions();
        $paths = array();
        foreach ($autoloaders as $autoloader) {
            // check to see if the callable is an object:method callable
            if (is_array($autoloader) && is_object($autoloader[0])) {
                $obj = $autoloader[0];

                if ($obj instanceof UniversalClassLoader) {
                    $paths = array_merge($paths, $obj->getPotentialPathsForClass($class));
                }
            }
        }

        if (0 == count($paths)) {
            return sprintf('Class "%s" could not be found - check the class name or autoloader.', $class);
        } elseif (1 == count($paths)) {
            $path = $paths[0];

            // the file doesn't exist
            if (!file_exists($path)) {
                return sprintf('Class "%s" could not be found at "%s" - the file does not exist.', $class, $path);
            }

            // the file exists, but the class is not in it
            return sprintf('The file "%s" was loaded, but the class "%s" was not found in it.', $path, $class);
        }

        // the class was looked for in multiple locations
        return sprintf('Class "%s" could not be found, but was looked for in the following locations: %s.', $class, implode(', ', $paths));
    }
}