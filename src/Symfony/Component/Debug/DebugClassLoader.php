<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

/**
 * Autoloader checking if the class is really defined in the file found.
 *
 * The ClassLoader will wrap all registered autoloaders
 * and will throw an exception if a file is found but does
 * not declare the class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @api
 */
class DebugClassLoader
{
    private $classLoader;
    private $isFinder;
    private $wasFinder;

    /**
     * Constructor.
     *
     * @param callable|object $classLoader
     *
     * @api
     * @deprecated since 2.5, passing an object is deprecated and support for it will be removed in 3.0
     */
    public function __construct($classLoader)
    {
        $this->wasFinder = is_object($classLoader) && method_exists($classLoader, 'findFile');

        if ($this->wasFinder) {
            $this->classLoader = array($classLoader, 'loadClass');
            $this->isFinder = true;
        } else {
            $this->classLoader = $classLoader;
            $this->isFinder = is_array($classLoader) && method_exists($classLoader[0], 'findFile');
        }
    }

    /**
     * Gets the wrapped class loader.
     *
     * @return callable|object a class loader
     *
     * @deprecated since 2.5, returning an object is deprecated and support for it will be removed in 3.0
     */
    public function getClassLoader()
    {
        if ($this->wasFinder) {
            return $this->classLoader[0];
        } else {
            return $this->classLoader;
        }
    }

    /**
     * Wraps all autoloaders
     */
    public static function enable()
    {
        // Ensures we don't hit https://bugs.php.net/42098
        class_exists(__NAMESPACE__.'\ErrorHandler', true);

        if (!is_array($functions = spl_autoload_functions())) {
            return;
        }

        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }

        foreach ($functions as $function) {
            if (!is_array($function) || !$function[0] instanceof self) {
                $function = array(new static($function), 'loadClass');
            }

            spl_autoload_register($function);
        }
    }

    /**
     * Disables the wrapping.
     */
    public static function disable()
    {
        if (!is_array($functions = spl_autoload_functions())) {
            return;
        }

        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }

        foreach ($functions as $function) {
            if (is_array($function) && $function[0] instanceof self) {
                $function = $function[0]->getClassLoader();
            }

            spl_autoload_register($function);
        }
    }

    /**
     * Finds a file by class name
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null
     *
     * @deprecated Deprecated since 2.5, to be removed in 3.0.
     */
    public function findFile($class)
    {
        if ($this->wasFinder) {
            return $this->classLoader[0]->findFile($class);
        }
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     *
     * @return Boolean|null True, if loaded
     *
     * @throws \RuntimeException
     */
    public function loadClass($class)
    {
        ErrorHandler::stackErrors();

        try {
            if ($this->isFinder) {
                if ($file = $this->classLoader[0]->findFile($class)) {
                    require $file;
                }
            } else {
                call_user_func($this->classLoader, $class);
                $file = false;
            }
        } catch (\Exception $e) {
            ErrorHandler::unstackErrors();

            throw $e;
        }

        ErrorHandler::unstackErrors();

        $exists = class_exists($class, false) || interface_exists($class, false) || (function_exists('trait_exists') && trait_exists($class, false));

        if ($exists) {
            $name = new \ReflectionClass($class);
            $name = $name->getName();

            if ($name !== $class) {
                throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: %s vs %s', $class, $name));
            }
        }

        if ($file) {
            if ('\\' == $class[0]) {
                $class = substr($class, 1);
            }

            $i = 0;
            $tail = DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
            $len = strlen($tail);

            do {
                $tail = substr($tail, $i);
                $len -= $i;

                if (0 === substr_compare($file, $tail, -$len, $len, true)) {
                    if (0 !== substr_compare($file, $tail, -$len, $len, false)) {
                        if (method_exists($this->classLoader[0], 'getClassMap')) {
                            $map = $this->classLoader[0]->getClassMap();
                        } else {
                            $map = array();
                        }

                        if (! isset($map[$class])) {
                            throw new \RuntimeException(sprintf('Case mismatch between class and source file names: %s vs %s', $class, $file));
                        }
                    }

                    break;
                }
            } while (false !== $i = strpos($tail, DIRECTORY_SEPARATOR, 1));

            if (! $exists) {
                if (false !== strpos($class, '/')) {
                    throw new \RuntimeException(sprintf('Trying to autoload a class with an invalid name "%s". Be careful that the namespace separator is "\" in PHP, not "/".', $class));
                }

                throw new \RuntimeException(sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
            }

            return true;
        }
    }
}
