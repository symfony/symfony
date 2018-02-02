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
    private $loaded = array();
    private static $caseCheck;
    private static $checkedClasses = array();
    private static $final = array();
    private static $finalMethods = array();
    private static $deprecated = array();
    private static $internal = array();
    private static $internalMethods = array();
    private static $php7Reserved = array('int' => 1, 'float' => 1, 'bool' => 1, 'string' => 1, 'true' => 1, 'false' => 1, 'null' => 1);
    private static $darwinCache = array('/' => array('/', array()));

    public function __construct(callable $classLoader)
    {
        $this->classLoader = $classLoader;
        $this->isFinder = is_array($classLoader) && method_exists($classLoader[0], 'findFile');

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
     * @return callable The wrapped class loader
     */
    public function getClassLoader()
    {
        return $this->classLoader;
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
        $e = error_reporting(error_reporting() | E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);

        try {
            if ($this->isFinder && !isset($this->loaded[$class])) {
                $this->loaded[$class] = true;
                if ($file = $this->classLoader[0]->findFile($class) ?: false) {
                    $wasCached = \function_exists('opcache_is_script_cached') && @opcache_is_script_cached($file);

                    require $file;

                    if ($wasCached) {
                        return;
                    }
                }
            } else {
                call_user_func($this->classLoader, $class);
                $file = false;
            }
        } finally {
            error_reporting($e);
        }

        $this->checkClass($class, $file);
    }

    private function checkClass($class, $file = null)
    {
        $exists = null === $file || \class_exists($class, false) || \interface_exists($class, false) || \trait_exists($class, false);

        if (null !== $file && $class && '\\' === $class[0]) {
            $class = substr($class, 1);
        }

        if ($exists) {
            if (isset(self::$checkedClasses[$class])) {
                return;
            }
            self::$checkedClasses[$class] = true;

            $refl = new \ReflectionClass($class);
            if (null === $file && $refl->isInternal()) {
                return;
            }
            $name = $refl->getName();

            if ($name !== $class && 0 === \strcasecmp($name, $class)) {
                throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: "%s" vs "%s".', $class, $name));
            }

            // Don't trigger deprecations for classes in the same vendor
            if (2 > $len = 1 + (\strpos($name, '\\') ?: \strpos($name, '_'))) {
                $len = 0;
                $ns = '';
            } else {
                $ns = \substr($name, 0, $len);
            }

            // Detect annotations on the class
            if (false !== $doc = $refl->getDocComment()) {
                foreach (array('final', 'deprecated', 'internal') as $annotation) {
                    if (false !== \strpos($doc, $annotation) && preg_match('#\n \* @'.$annotation.'(?:( .+?)\.?)?\r?\n \*(?: @|/$)#s', $doc, $notice)) {
                        self::${$annotation}[$name] = isset($notice[1]) ? preg_replace('#\s*\r?\n \* +#', ' ', $notice[1]) : '';
                    }
                }
            }

            $parentAndTraits = \class_uses($name, false);
            if ($parent = \get_parent_class($class)) {
                $parentAndTraits[] = $parent;

                if (!isset(self::$checkedClasses[$parent])) {
                    $this->checkClass($parent);
                }

                if (isset(self::$final[$parent])) {
                    @trigger_error(sprintf('The "%s" class is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $parent, self::$final[$parent], $name), E_USER_DEPRECATED);
                }
            }

            // Detect if the parent is annotated
            foreach ($parentAndTraits + $this->getOwnInterfaces($name, $parent) as $use) {
                if (!isset(self::$checkedClasses[$use])) {
                    $this->checkClass($use);
                }
                if (isset(self::$deprecated[$use]) && \strncmp($ns, $use, $len)) {
                    $type = class_exists($name, false) ? 'class' : (interface_exists($name, false) ? 'interface' : 'trait');
                    $verb = class_exists($use, false) || interface_exists($name, false) ? 'extends' : (interface_exists($use, false) ? 'implements' : 'uses');

                    @trigger_error(sprintf('The "%s" %s %s "%s" that is deprecated%s.', $name, $type, $verb, $use, self::$deprecated[$use]), E_USER_DEPRECATED);
                }
                if (isset(self::$internal[$use]) && \strncmp($ns, $use, $len)) {
                    @trigger_error(sprintf('The "%s" %s is considered internal%s. It may change without further notice. You should not use it from "%s".', $use, class_exists($use, false) ? 'class' : (interface_exists($use, false) ? 'interface' : 'trait'), self::$internal[$use], $name), E_USER_DEPRECATED);
                }
            }

            // Inherit @final and @internal annotations for methods
            self::$finalMethods[$name] = array();
            self::$internalMethods[$name] = array();
            foreach ($parentAndTraits as $use) {
                foreach (array('finalMethods', 'internalMethods') as $property) {
                    if (isset(self::${$property}[$use])) {
                        self::${$property}[$name] = self::${$property}[$name] ? self::${$property}[$use] + self::${$property}[$name] : self::${$property}[$use];
                    }
                }
            }

            $isClass = \class_exists($name, false);
            foreach ($refl->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if ($method->class !== $name) {
                    continue;
                }

                // Method from a trait
                if ($method->getFilename() !== $refl->getFileName()) {
                    continue;
                }

                if ($isClass && $parent && isset(self::$finalMethods[$parent][$method->name])) {
                    list($declaringClass, $message) = self::$finalMethods[$parent][$method->name];
                    @trigger_error(sprintf('The "%s::%s()" method is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $declaringClass, $method->name, $message, $name), E_USER_DEPRECATED);
                }

                foreach ($parentAndTraits as $use) {
                    if (isset(self::$internalMethods[$use][$method->name])) {
                        list($declaringClass, $message) = self::$internalMethods[$use][$method->name];
                        if (\strncmp($ns, $declaringClass, $len)) {
                            @trigger_error(sprintf('The "%s::%s()" method is considered internal%s. It may change without further notice. You should not extend it from "%s".', $declaringClass, $method->name, $message, $name), E_USER_DEPRECATED);
                        }
                    }
                }

                // Detect method annotations
                if (false === $doc = $method->getDocComment()) {
                    continue;
                }

                foreach (array('final', 'internal') as $annotation) {
                    if (false !== \strpos($doc, $annotation) && preg_match('#\n\s+\* @'.$annotation.'(?:( .+?)\.?)?\r?\n\s+\*(?: @|/$)#s', $doc, $notice)) {
                        $message = isset($notice[1]) ? preg_replace('#\s*\r?\n \* +#', ' ', $notice[1]) : '';
                        self::${$annotation.'Methods'}[$name][$method->name] = array($name, $message);
                    }
                }
            }

            if (isset(self::$php7Reserved[\strtolower($refl->getShortName())])) {
                @trigger_error(sprintf('The "%s" class uses the reserved name "%s", it will break on PHP 7 and higher', $name, $refl->getShortName()), E_USER_DEPRECATED);
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
                    throw new \RuntimeException(sprintf('Case mismatch between class and real file names: "%s" vs "%s" in "%s".', substr($tail, -$tailLen + 1), substr($real, -$tailLen + 1), substr($real, 0, -$tailLen + 1)));
                }
            }
        }
    }

    /**
     * `class_implements` includes interfaces from the parents so we have to manually exclude them.
     *
     * @param string       $class
     * @param string|false $parent
     *
     * @return string[]
     */
    private function getOwnInterfaces($class, $parent)
    {
        $ownInterfaces = class_implements($class, false);

        if ($parent) {
            foreach (class_implements($parent, false) as $interface) {
                unset($ownInterfaces[$interface]);
            }
        }

        foreach ($ownInterfaces as $interface) {
            foreach (class_implements($interface) as $interface) {
                unset($ownInterfaces[$interface]);
            }
        }

        return $ownInterfaces;
    }
}
