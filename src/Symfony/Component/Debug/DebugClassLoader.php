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

use PHPUnit\Framework\MockObject\Matcher\StatelessInvocation;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', DebugClassLoader::class, \Symfony\Component\ErrorHandler\DebugClassLoader::class), \E_USER_DEPRECATED);

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
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 *
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\DebugClassLoader instead.
 */
class DebugClassLoader
{
    private $classLoader;
    private $isFinder;
    private $loaded = [];
    private static $caseCheck;
    private static $checkedClasses = [];
    private static $final = [];
    private static $finalMethods = [];
    private static $deprecated = [];
    private static $internal = [];
    private static $internalMethods = [];
    private static $annotatedParameters = [];
    private static $darwinCache = ['/' => ['/', []]];
    private static $method = [];

    public function __construct(callable $classLoader)
    {
        $this->classLoader = $classLoader;
        $this->isFinder = \is_array($classLoader) && method_exists($classLoader[0], 'findFile');

        if (!isset(self::$caseCheck)) {
            $file = file_exists(__FILE__) ? __FILE__ : rtrim(realpath('.'), \DIRECTORY_SEPARATOR);
            $i = strrpos($file, \DIRECTORY_SEPARATOR);
            $dir = substr($file, 0, 1 + $i);
            $file = substr($file, 1 + $i);
            $test = strtoupper($file) === $file ? strtolower($file) : strtoupper($file);
            $test = realpath($dir.$test);

            if (false === $test || false === $i) {
                // filesystem is case sensitive
                self::$caseCheck = 0;
            } elseif (substr($test, -\strlen($file)) === $file) {
                // filesystem is case insensitive and realpath() normalizes the case of characters
                self::$caseCheck = 1;
            } elseif (false !== stripos(\PHP_OS, 'darwin')) {
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

        if (!\is_array($functions = spl_autoload_functions())) {
            return;
        }

        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }

        foreach ($functions as $function) {
            if (!\is_array($function) || !$function[0] instanceof self) {
                $function = [new static($function), 'loadClass'];
            }

            spl_autoload_register($function);
        }
    }

    /**
     * Disables the wrapping.
     */
    public static function disable()
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return;
        }

        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }

        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof self) {
                $function = $function[0]->getClassLoader();
            }

            spl_autoload_register($function);
        }
    }

    /**
     * @return string|null
     */
    public function findFile($class)
    {
        return $this->isFinder ? $this->classLoader[0]->findFile($class) ?: null : null;
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     *
     * @throws \RuntimeException
     */
    public function loadClass($class)
    {
        $e = error_reporting(error_reporting() | \E_PARSE | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR);

        try {
            if ($this->isFinder && !isset($this->loaded[$class])) {
                $this->loaded[$class] = true;
                if (!$file = $this->classLoader[0]->findFile($class) ?: false) {
                    // no-op
                } elseif (\function_exists('opcache_is_script_cached') && @opcache_is_script_cached($file)) {
                    include $file;

                    return;
                } elseif (false === include $file) {
                    return;
                }
            } else {
                ($this->classLoader)($class);
                $file = false;
            }
        } finally {
            error_reporting($e);
        }

        $this->checkClass($class, $file);
    }

    private function checkClass(string $class, string $file = null)
    {
        $exists = null === $file || class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);

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

            if ($name !== $class && 0 === strcasecmp($name, $class)) {
                throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: "%s" vs "%s".', $class, $name));
            }

            $deprecations = $this->checkAnnotations($refl, $name);

            foreach ($deprecations as $message) {
                @trigger_error($message, \E_USER_DEPRECATED);
            }
        }

        if (!$file) {
            return;
        }

        if (!$exists) {
            if (false !== strpos($class, '/')) {
                throw new \RuntimeException(sprintf('Trying to autoload a class with an invalid name "%s". Be careful that the namespace separator is "\" in PHP, not "/".', $class));
            }

            throw new \RuntimeException(sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
        }

        if (self::$caseCheck && $message = $this->checkCase($refl, $file, $class)) {
            throw new \RuntimeException(sprintf('Case mismatch between class and real file names: "%s" vs "%s" in "%s".', $message[0], $message[1], $message[2]));
        }
    }

    public function checkAnnotations(\ReflectionClass $refl, $class)
    {
        $deprecations = [];

        // Don't trigger deprecations for classes in the same vendor
        if (2 > $len = 1 + (strpos($class, '\\') ?: strpos($class, '_'))) {
            $len = 0;
            $ns = '';
        } else {
            $ns = str_replace('_', '\\', substr($class, 0, $len));
        }

        // Detect annotations on the class
        if (false !== $doc = $refl->getDocComment()) {
            foreach (['final', 'deprecated', 'internal'] as $annotation) {
                if (false !== strpos($doc, $annotation) && preg_match('#\n\s+\* @'.$annotation.'(?:( .+?)\.?)?\r?\n\s+\*(?: @|/$|\r?\n)#s', $doc, $notice)) {
                    self::${$annotation}[$class] = isset($notice[1]) ? preg_replace('#\.?\r?\n( \*)? *(?= |\r?\n|$)#', '', $notice[1]) : '';
                }
            }

            if ($refl->isInterface() && false !== strpos($doc, 'method') && preg_match_all('#\n \* @method\s+(static\s+)?+(?:[\w\|&\[\]\\\]+\s+)?(\w+(?:\s*\([^\)]*\))?)+(.+?([[:punct:]]\s*)?)?(?=\r?\n \*(?: @|/$|\r?\n))#', $doc, $notice, \PREG_SET_ORDER)) {
                foreach ($notice as $method) {
                    $static = '' !== $method[1];
                    $name = $method[2];
                    $description = $method[3] ?? null;
                    if (false === strpos($name, '(')) {
                        $name .= '()';
                    }
                    if (null !== $description) {
                        $description = trim($description);
                        if (!isset($method[4])) {
                            $description .= '.';
                        }
                    }
                    self::$method[$class][] = [$class, $name, $static, $description];
                }
            }
        }

        $parent = get_parent_class($class);
        $parentAndOwnInterfaces = $this->getOwnInterfaces($class, $parent ?: null);
        if ($parent) {
            $parentAndOwnInterfaces[$parent] = $parent;

            if (!isset(self::$checkedClasses[$parent])) {
                $this->checkClass($parent);
            }

            if (isset(self::$final[$parent])) {
                $deprecations[] = sprintf('The "%s" class is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $parent, self::$final[$parent], $class);
            }
        }

        // Detect if the parent is annotated
        foreach ($parentAndOwnInterfaces + class_uses($class, false) as $use) {
            if (!isset(self::$checkedClasses[$use])) {
                $this->checkClass($use);
            }
            if (isset(self::$deprecated[$use]) && strncmp($ns, str_replace('_', '\\', $use), $len) && !isset(self::$deprecated[$class])) {
                $type = class_exists($class, false) ? 'class' : (interface_exists($class, false) ? 'interface' : 'trait');
                $verb = class_exists($use, false) || interface_exists($class, false) ? 'extends' : (interface_exists($use, false) ? 'implements' : 'uses');

                $deprecations[] = sprintf('The "%s" %s %s "%s" that is deprecated%s.', $class, $type, $verb, $use, self::$deprecated[$use]);
            }
            if (isset(self::$internal[$use]) && strncmp($ns, str_replace('_', '\\', $use), $len)) {
                $deprecations[] = sprintf('The "%s" %s is considered internal%s. It may change without further notice. You should not use it from "%s".', $use, class_exists($use, false) ? 'class' : (interface_exists($use, false) ? 'interface' : 'trait'), self::$internal[$use], $class);
            }
            if (isset(self::$method[$use])) {
                if ($refl->isAbstract()) {
                    if (isset(self::$method[$class])) {
                        self::$method[$class] = array_merge(self::$method[$class], self::$method[$use]);
                    } else {
                        self::$method[$class] = self::$method[$use];
                    }
                } elseif (!$refl->isInterface()) {
                    $hasCall = $refl->hasMethod('__call');
                    $hasStaticCall = $refl->hasMethod('__callStatic');
                    foreach (self::$method[$use] as $method) {
                        list($interface, $name, $static, $description) = $method;
                        if ($static ? $hasStaticCall : $hasCall) {
                            continue;
                        }
                        $realName = substr($name, 0, strpos($name, '('));
                        if (!$refl->hasMethod($realName) || !($methodRefl = $refl->getMethod($realName))->isPublic() || ($static && !$methodRefl->isStatic()) || (!$static && $methodRefl->isStatic())) {
                            $deprecations[] = sprintf('Class "%s" should implement method "%s::%s"%s', $class, ($static ? 'static ' : '').$interface, $name, null == $description ? '.' : ': '.$description);
                        }
                    }
                }
            }
        }

        if (trait_exists($class)) {
            return $deprecations;
        }

        // Inherit @final, @internal and @param annotations for methods
        self::$finalMethods[$class] = [];
        self::$internalMethods[$class] = [];
        self::$annotatedParameters[$class] = [];
        foreach ($parentAndOwnInterfaces as $use) {
            foreach (['finalMethods', 'internalMethods', 'annotatedParameters'] as $property) {
                if (isset(self::${$property}[$use])) {
                    self::${$property}[$class] = self::${$property}[$class] ? self::${$property}[$use] + self::${$property}[$class] : self::${$property}[$use];
                }
            }
        }

        foreach ($refl->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->class !== $class) {
                continue;
            }

            if ($parent && isset(self::$finalMethods[$parent][$method->name])) {
                list($declaringClass, $message) = self::$finalMethods[$parent][$method->name];
                $deprecations[] = sprintf('The "%s::%s()" method is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $declaringClass, $method->name, $message, $class);
            }

            if (isset(self::$internalMethods[$class][$method->name])) {
                list($declaringClass, $message) = self::$internalMethods[$class][$method->name];
                if (strncmp($ns, $declaringClass, $len)) {
                    $deprecations[] = sprintf('The "%s::%s()" method is considered internal%s. It may change without further notice. You should not extend it from "%s".', $declaringClass, $method->name, $message, $class);
                }
            }

            // To read method annotations
            $doc = $method->getDocComment();

            if (isset(self::$annotatedParameters[$class][$method->name])) {
                $definedParameters = [];
                foreach ($method->getParameters() as $parameter) {
                    $definedParameters[$parameter->name] = true;
                }

                foreach (self::$annotatedParameters[$class][$method->name] as $parameterName => $deprecation) {
                    if (!isset($definedParameters[$parameterName]) && !($doc && preg_match("/\\n\\s+\\* @param +((?(?!callable *\().*?|callable *\(.*\).*?))(?<= )\\\${$parameterName}\\b/", $doc))) {
                        $deprecations[] = sprintf($deprecation, $class);
                    }
                }
            }

            if (!$doc) {
                continue;
            }

            $finalOrInternal = false;

            foreach (['final', 'internal'] as $annotation) {
                if (false !== strpos($doc, $annotation) && preg_match('#\n\s+\* @'.$annotation.'(?:( .+?)\.?)?\r?\n\s+\*(?: @|/$|\r?\n)#s', $doc, $notice)) {
                    $message = isset($notice[1]) ? preg_replace('#\.?\r?\n( \*)? *(?= |\r?\n|$)#', '', $notice[1]) : '';
                    self::${$annotation.'Methods'}[$class][$method->name] = [$class, $message];
                    $finalOrInternal = true;
                }
            }

            if ($finalOrInternal || $method->isConstructor() || false === strpos($doc, '@param') || StatelessInvocation::class === $class) {
                continue;
            }
            if (!preg_match_all('#\n\s+\* @param +((?(?!callable *\().*?|callable *\(.*\).*?))(?<= )\$([a-zA-Z0-9_\x7f-\xff]++)#', $doc, $matches, \PREG_SET_ORDER)) {
                continue;
            }
            if (!isset(self::$annotatedParameters[$class][$method->name])) {
                $definedParameters = [];
                foreach ($method->getParameters() as $parameter) {
                    $definedParameters[$parameter->name] = true;
                }
            }
            foreach ($matches as list(, $parameterType, $parameterName)) {
                if (!isset($definedParameters[$parameterName])) {
                    $parameterType = trim($parameterType);
                    self::$annotatedParameters[$class][$method->name][$parameterName] = sprintf('The "%%s::%s()" method will require a new "%s$%s" argument in the next major version of its %s "%s", not defining it is deprecated.', $method->name, $parameterType ? $parameterType.' ' : '', $parameterName, interface_exists($class) ? 'interface' : 'parent class', $method->class);
                }
            }
        }

        return $deprecations;
    }

    /**
     * @param string $file
     * @param string $class
     *
     * @return array|null
     */
    public function checkCase(\ReflectionClass $refl, $file, $class)
    {
        $real = explode('\\', $class.strrchr($file, '.'));
        $tail = explode(\DIRECTORY_SEPARATOR, str_replace('/', \DIRECTORY_SEPARATOR, $file));

        $i = \count($tail) - 1;
        $j = \count($real) - 1;

        while (isset($tail[$i], $real[$j]) && $tail[$i] === $real[$j]) {
            --$i;
            --$j;
        }

        array_splice($tail, 0, $i + 1);

        if (!$tail) {
            return null;
        }

        $tail = \DIRECTORY_SEPARATOR.implode(\DIRECTORY_SEPARATOR, $tail);
        $tailLen = \strlen($tail);
        $real = $refl->getFileName();

        if (2 === self::$caseCheck) {
            $real = $this->darwinRealpath($real);
        }

        if (0 === substr_compare($real, $tail, -$tailLen, $tailLen, true)
            && 0 !== substr_compare($real, $tail, -$tailLen, $tailLen, false)
        ) {
            return [substr($tail, -$tailLen + 1), substr($real, -$tailLen + 1), substr($real, 0, -$tailLen + 1)];
        }

        return null;
    }

    /**
     * `realpath` on MacOSX doesn't normalize the case of characters.
     */
    private function darwinRealpath(string $real): string
    {
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

                if (!@chdir($real)) {
                    return $real.$file;
                }

                $real = getcwd().'/';
                chdir($dir);

                $dir = $real;
                $k = $kDir;
                $i = \strlen($dir) - 1;
                while (!isset(self::$darwinCache[$k])) {
                    self::$darwinCache[$k] = [$dir, []];
                    self::$darwinCache[$dir] = &self::$darwinCache[$k];

                    while ('/' !== $dir[--$i]) {
                    }
                    $k = substr($k, 0, ++$i);
                    $dir = substr($dir, 0, $i--);
                }
            }
        }

        $dirFiles = self::$darwinCache[$kDir][1];

        if (!isset($dirFiles[$file]) && ') : eval()\'d code' === substr($file, -17)) {
            // Get the file name from "file_name.php(123) : eval()'d code"
            $file = substr($file, 0, strrpos($file, '(', -17));
        }

        if (isset($dirFiles[$file])) {
            return $real.$dirFiles[$file];
        }

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

        return $real.$dirFiles[$kFile];
    }

    /**
     * `class_implements` includes interfaces from the parents so we have to manually exclude them.
     *
     * @return string[]
     */
    private function getOwnInterfaces(string $class, ?string $parent): array
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
