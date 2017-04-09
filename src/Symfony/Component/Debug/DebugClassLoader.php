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
 */
class DebugClassLoader
{
    private $classLoader;
    private $isFinder;
    private $wasFinder;
    private static $caseCheck;
    private static $deprecated = array();
    private static $php7Reserved = array('int', 'float', 'bool', 'string', 'true', 'false', 'null');
    private static $darwinCache = array('/' => array('/', array()));

    /**
     * Constructor.
     *
     * @param callable|object $classLoader Passing an object is @deprecated since version 2.5 and support for it will be removed in 3.0
     */
    public function __construct($classLoader)
    {
        $this->wasFinder = is_object($classLoader) && method_exists($classLoader, 'findFile');

        if ($this->wasFinder) {
            @trigger_error('The '.__METHOD__.' method will no longer support receiving an object into its $classLoader argument in 3.0.', E_USER_DEPRECATED);
            $this->classLoader = array($classLoader, 'loadClass');
            $this->isFinder = true;
        } else {
            $this->classLoader = $classLoader;
            $this->isFinder = is_array($classLoader) && method_exists($classLoader[0], 'findFile');
        }

        if (!isset(self::$caseCheck)) {
            $file = file_exists(__FILE__) ? __FILE__ : rtrim(realpath('.'), DIRECTORY_SEPARATOR);
            $i = strrpos($file, DIRECTORY_SEPARATOR);
            $dir = substr($file, 0, 1 + $i);
            $file = substr($file, 1 + $i);
            $test = strtoupper($file) === $file ? strtolower($file) : strtoupper($file);
            $test = realpath($dir.$test);

            if (false === $test || false === $i) {
                // filesystem is case sensitive
                self::$caseCheck = 0;
            } elseif (substr($test, -strlen($file)) === $file) {
                // filesystem is case insensitive and realpath() normalizes the case of characters
                self::$caseCheck = 1;
            } elseif (false !== stripos(PHP_OS, 'darwin')) {
                // on MacOSX, HFS+ is case insensitive but realpath() doesn't normalize the case of characters
                self::$caseCheck = 2;
            } else {
                // filesystem case checks failed, fallback to disabling them
                self::$caseCheck = 0;
            }
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
     * Wraps all autoloaders.
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
     * Finds a file by class name.
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null
     *
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    public function findFile($class)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

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
                    require_once $file;
                }
            } else {
                call_user_func($this->classLoader, $class);
                $file = false;
            }
        } catch (\Exception $e) {
            ErrorHandler::unstackErrors();

            throw $e;
        } catch (\Throwable $e) {
            ErrorHandler::unstackErrors();

            throw $e;
        }

        ErrorHandler::unstackErrors();

        $exists = class_exists($class, false) || interface_exists($class, false) || (function_exists('trait_exists') && trait_exists($class, false));

        if ($class && '\\' === $class[0]) {
            $class = substr($class, 1);
        }

        if ($exists) {
            $refl = new \ReflectionClass($class);
            $name = $refl->getName();

            if ($name !== $class && 0 === strcasecmp($name, $class)) {
                throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: %s vs %s', $class, $name));
            }

            if (in_array(strtolower($refl->getShortName()), self::$php7Reserved)) {
                @trigger_error(sprintf('%s uses a reserved class name (%s) that will break on PHP 7 and higher', $name, $refl->getShortName()), E_USER_DEPRECATED);
            } elseif (preg_match('#\n \* @deprecated (.*?)\r?\n \*(?: @|/$)#s', $refl->getDocComment(), $notice)) {
                self::$deprecated[$name] = preg_replace('#\s*\r?\n \* +#', ' ', $notice[1]);
            } else {
                if (2 > $len = 1 + (strpos($name, '\\', 1 + strpos($name, '\\')) ?: strpos($name, '_'))) {
                    $len = 0;
                    $ns = '';
                } else {
                    switch ($ns = substr($name, 0, $len)) {
                        case 'Symfony\Bridge\\':
                        case 'Symfony\Bundle\\':
                        case 'Symfony\Component\\':
                            $ns = 'Symfony\\';
                            $len = strlen($ns);
                            break;
                    }
                }
                $parent = get_parent_class($class);

                if (!$parent || strncmp($ns, $parent, $len)) {
                    if ($parent && isset(self::$deprecated[$parent]) && strncmp($ns, $parent, $len)) {
                        @trigger_error(sprintf('The %s class extends %s that is deprecated %s', $name, $parent, self::$deprecated[$parent]), E_USER_DEPRECATED);
                    }

                    $parentInterfaces = array();
                    $deprecatedInterfaces = array();
                    if ($parent) {
                        foreach (class_implements($parent) as $interface) {
                            $parentInterfaces[$interface] = 1;
                        }
                    }

                    foreach ($refl->getInterfaceNames() as $interface) {
                        if (isset(self::$deprecated[$interface]) && strncmp($ns, $interface, $len)) {
                            $deprecatedInterfaces[] = $interface;
                        }
                        foreach (class_implements($interface) as $interface) {
                            $parentInterfaces[$interface] = 1;
                        }
                    }

                    foreach ($deprecatedInterfaces as $interface) {
                        if (!isset($parentInterfaces[$interface])) {
                            @trigger_error(sprintf('The %s %s %s that is deprecated %s', $name, $refl->isInterface() ? 'interface extends' : 'class implements', $interface, self::$deprecated[$interface]), E_USER_DEPRECATED);
                        }
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
            if (self::$caseCheck) {
                $real = explode('\\', $class.strrchr($file, '.'));
                $tail = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $file));

                $i = count($tail) - 1;
                $j = count($real) - 1;

                while (isset($tail[$i], $real[$j]) && $tail[$i] === $real[$j]) {
                    --$i;
                    --$j;
                }

                array_splice($tail, 0, $i + 1);
            }
            if (self::$caseCheck && $tail) {
                $tail = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $tail);
                $tailLen = strlen($tail);
                $real = $refl->getFileName();

                if (2 === self::$caseCheck) {
                    // realpath() on MacOSX doesn't normalize the case of characters

                    $i = 1 + strrpos($real, '/');
                    $file = substr($real, $i);
                    $real = substr($real, 0, $i);

                    if (isset(self::$darwinCache[$real])) {
                        $kDir = $real;
                    } else {
                        $kDir = strtolower($real);

                        if (isset(self::$darwinCache[$kDir])) {
                            $real = self::$darwinCache[$kDir][0];
                        } else {
                            $dir = getcwd();
                            chdir($real);
                            $real = getcwd().'/';
                            chdir($dir);

                            $dir = $real;
                            $k = $kDir;
                            $i = strlen($dir) - 1;
                            while (!isset(self::$darwinCache[$k])) {
                                self::$darwinCache[$k] = array($dir, array());
                                self::$darwinCache[$dir] = &self::$darwinCache[$k];

                                while ('/' !== $dir[--$i]) {
                                }
                                $k = substr($k, 0, ++$i);
                                $dir = substr($dir, 0, $i--);
                            }
                        }
                    }

                    $dirFiles = self::$darwinCache[$kDir][1];

                    if (isset($dirFiles[$file])) {
                        $kFile = $file;
                    } else {
                        $kFile = strtolower($file);

                        if (!isset($dirFiles[$kFile])) {
                            foreach (scandir($real, 2) as $f) {
                                if ('.' !== $f[0]) {
                                    $dirFiles[$f] = $f;
                                    if ($f === $file) {
                                        $kFile = $k = $file;
                                    } elseif ($f !== $k = strtolower($f)) {
                                        $dirFiles[$k] = $f;
                                    }
                                }
                            }
                            self::$darwinCache[$kDir][1] = $dirFiles;
                        }
                    }

                    $real .= $dirFiles[$kFile];
                }

                if (0 === substr_compare($real, $tail, -$tailLen, $tailLen, true)
                  && 0 !== substr_compare($real, $tail, -$tailLen, $tailLen, false)
                ) {
                    throw new \RuntimeException(sprintf('Case mismatch between class and real file names: %s vs %s in %s', substr($tail, -$tailLen + 1), substr($real, -$tailLen + 1), substr($real, 0, -$tailLen + 1)));
                }
            }

            return true;
        }
    }
}
