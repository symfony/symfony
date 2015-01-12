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
    private static $caseCheck;
    private static $deprecated = array();

    /**
     * Constructor.
     *
     * @param callable|object $classLoader Passing an object is @deprecated since version 2.5 and support for it will be removed in 3.0
     *
     * @api
     */
    public function __construct($classLoader)
    {
        $this->wasFinder = is_object($classLoader) && method_exists($classLoader, 'findFile');

        if ($this->wasFinder) {
            trigger_error('The '.__METHOD__.' method will no longer support receiving an object into its $classLoader argument in 3.0.', E_USER_DEPRECATED);
            $this->classLoader = array($classLoader, 'loadClass');
            $this->isFinder = true;
        } else {
            $this->classLoader = $classLoader;
            $this->isFinder = is_array($classLoader) && method_exists($classLoader[0], 'findFile');
        }

        if (!isset(self::$caseCheck)) {
            self::$caseCheck = false !== stripos(PHP_OS, 'win') ? (false !== stripos(PHP_OS, 'darwin') ? 2 : 1) : 0;
        }
    }

    /**
     * Gets the wrapped class loader.
     *
     * @return callable|object A class loader. Since version 2.5, returning an object is @deprecated and support for it will be removed in 3.0
     */
    public function getClassLoader()
    {
        return $this->wasFinder ? $this->classLoader[0] : $this->classLoader;
    }

    /**
     * Wraps all autoloaders
     */
    public static function enable()
    {
        // Ensures we don't hit https://bugs.php.net/42098
        class_exists('Symfony\Component\Debug\ErrorHandler');
        class_exists('Psr\Log\LogLevel');

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
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    public function findFile($class)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

        if ($this->wasFinder) {
            return $this->classLoader[0]->findFile($class);
        }
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     *
     * @return bool|null True, if loaded
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

        $exists = class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);

        if ('\\' === $class[0]) {
            $class = substr($class, 1);
        }

        if ($exists) {
            $refl = new \ReflectionClass($class);
            $name = $refl->getName();

            if ($name !== $class && 0 === strcasecmp($name, $class)) {
                throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: %s vs %s', $class, $name));
            }

            if (preg_match('#\n \* @deprecated (.*?)\r?\n \*(?: @|/$)#s', $refl->getDocComment(), $notice)) {
                self::$deprecated[$name] = preg_replace('#\s*\r?\n \* +#', ' ', $notice[1]);
            } elseif (0 !== strpos($name, 'Symfony\\')) {
                $parent = $refl->getParentClass();

                if ($parent && isset(self::$deprecated[$parent->name])) {
                    trigger_error(sprintf('The %s class extends %s that is deprecated %s', $name, $parent->name, self::$deprecated[$parent->name]), E_USER_DEPRECATED);
                }

                foreach ($refl->getInterfaceNames() as $interface) {
                    if (isset(self::$deprecated[$interface]) && !($parent && $parent->implementsInterface($interface))) {
                        trigger_error(sprintf('The %s %s %s that is deprecated %s', $name, $refl->isInterface() ? 'interface extends' : 'class implements', $interface, self::$deprecated[$interface]), E_USER_DEPRECATED);
                    }
                }
            }
        }

        if ($file) {
            if (!$exists) {
                if (false !== strpos($class, '/')) {
                    throw new \RuntimeException(sprintf('Trying to autoload a class with an invalid name "%s". Be careful that the namespace separator is "\" in PHP, not "/".', $class));
                }

                throw new \RuntimeException(sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
            }
            if (self::$caseCheck && preg_match('#([/\\\\][a-zA-Z_\x7F-\xFF][a-zA-Z0-9_\x7F-\xFF]*)+\.(php|hh)$#D', $file, $tail)) {
                $tail = $tail[0];
                $real = $refl->getFilename();

                if (2 === self::$caseCheck) {
                    // realpath() on MacOSX doesn't normalize the case of characters
                    $cwd = getcwd();
                    $basename = strrpos($real, '/');
                    chdir(substr($real, 0, $basename));
                    $basename = substr($real, $basename + 1);
                    // glob() patterns are case-sensitive even if the underlying fs is not
                    if (!in_array($basename, glob($basename.'*', GLOB_NOSORT), true)) {
                        $real = getcwd().'/';
                        $h = opendir('.');
                        while (false !== $f = readdir($h)) {
                            if (0 === strcasecmp($f, $basename)) {
                                $real .= $f;
                                break;
                            }
                        }
                        closedir($h);
                    }
                    chdir($cwd);
                }

                if (0 === substr_compare($real, $tail, -strlen($tail), strlen($tail), true)
                  && 0 !== substr_compare($real, $tail, -strlen($tail), strlen($tail), false)
                ) {
                    throw new \RuntimeException(sprintf('Case mismatch between class and source file names: %s vs %s', $class, $real));
                }
            }

            return true;
        }
    }
}
