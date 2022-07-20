<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

use Composer\InstalledVersions;
use Doctrine\Common\Persistence\Proxy as LegacyProxy;
use Doctrine\Persistence\Proxy;
use Mockery\MockInterface;
use Phake\IMock;
use PHPUnit\Framework\MockObject\Matcher\StatelessInvocation;
use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\Prophecy\ProphecySubjectInterface;
use ProxyManager\Proxy\ProxyInterface;

/**
 * Autoloader checking if the class is really defined in the file found.
 *
 * The ClassLoader will wrap all registered autoloaders
 * and will throw an exception if a file is found but does
 * not declare the class.
 *
 * It can also patch classes to turn docblocks into actual return types.
 * This behavior is controlled by the SYMFONY_PATCH_TYPE_DECLARATIONS env var,
 * which is a url-encoded array with the follow parameters:
 *  - "force": any value enables deprecation notices - can be any of:
 *      - "docblock" to patch only docblock annotations
 *      - "object" to turn union types to the "object" type when possible (not recommended)
 *      - "1" to add all possible return types including magic methods
 *      - "0" to add possible return types excluding magic methods
 *  - "php": the target version of PHP - e.g. "7.1" doesn't generate "object" types
 *  - "deprecations": "1" to trigger a deprecation notice when a child class misses a
 *                    return type while the parent declares an "@return" annotation
 *
 * Note that patching doesn't care about any coding style so you'd better to run
 * php-cs-fixer after, with rules "phpdoc_trim_consecutive_blank_line_separation"
 * and "no_superfluous_phpdoc_tags" enabled typically.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
class DebugClassLoader
{
    private const SPECIAL_RETURN_TYPES = [
        'void' => 'void',
        'null' => 'null',
        'resource' => 'resource',
        'boolean' => 'bool',
        'true' => 'bool',
        'false' => 'bool',
        'integer' => 'int',
        'array' => 'array',
        'bool' => 'bool',
        'callable' => 'callable',
        'float' => 'float',
        'int' => 'int',
        'iterable' => 'iterable',
        'object' => 'object',
        'string' => 'string',
        'self' => 'self',
        'parent' => 'parent',
        'mixed' => 'mixed',
        'list' => 'array',
        'class-string' => 'string',
    ] + (\PHP_VERSION_ID >= 80000 ? [
        'static' => 'static',
        '$this' => 'static',
    ] : [
        'static' => 'object',
        '$this' => 'object',
    ]);

    private const BUILTIN_RETURN_TYPES = [
        'void' => true,
        'array' => true,
        'bool' => true,
        'callable' => true,
        'float' => true,
        'int' => true,
        'iterable' => true,
        'object' => true,
        'string' => true,
        'self' => true,
        'parent' => true,
    ] + (\PHP_VERSION_ID >= 80000 ? [
        'mixed' => true,
        'static' => true,
    ] : []);

    private const MAGIC_METHODS = [
        '__set' => 'void',
        '__isset' => 'bool',
        '__unset' => 'void',
        '__sleep' => 'array',
        '__wakeup' => 'void',
        '__toString' => 'string',
        '__clone' => 'void',
        '__debugInfo' => 'array',
        '__serialize' => 'array',
        '__unserialize' => 'void',
    ];

    private const INTERNAL_TYPES = [
        'ArrayAccess' => [
            'offsetExists' => 'bool',
            'offsetSet' => 'void',
            'offsetUnset' => 'void',
        ],
        'Countable' => [
            'count' => 'int',
        ],
        'Iterator' => [
            'next' => 'void',
            'valid' => 'bool',
            'rewind' => 'void',
        ],
        'IteratorAggregate' => [
            'getIterator' => '\Traversable',
        ],
        'OuterIterator' => [
            'getInnerIterator' => '\Iterator',
        ],
        'RecursiveIterator' => [
            'hasChildren' => 'bool',
        ],
        'SeekableIterator' => [
            'seek' => 'void',
        ],
        'Serializable' => [
            'serialize' => 'string',
            'unserialize' => 'void',
        ],
        'SessionHandlerInterface' => [
            'open' => 'bool',
            'close' => 'bool',
            'read' => 'string',
            'write' => 'bool',
            'destroy' => 'bool',
            'gc' => 'bool',
        ],
        'SessionIdInterface' => [
            'create_sid' => 'string',
        ],
        'SessionUpdateTimestampHandlerInterface' => [
            'validateId' => 'bool',
            'updateTimestamp' => 'bool',
        ],
        'Throwable' => [
            'getMessage' => 'string',
            'getCode' => 'int',
            'getFile' => 'string',
            'getLine' => 'int',
            'getTrace' => 'array',
            'getPrevious' => '?\Throwable',
            'getTraceAsString' => 'string',
        ],
    ];

    private $classLoader;
    private $isFinder;
    private $loaded = [];
    private $patchTypes;

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
    private static $returnTypes = [];
    private static $methodTraits = [];
    private static $fileOffsets = [];

    public function __construct(callable $classLoader)
    {
        $this->classLoader = $classLoader;
        $this->isFinder = \is_array($classLoader) && method_exists($classLoader[0], 'findFile');
        parse_str(getenv('SYMFONY_PATCH_TYPE_DECLARATIONS') ?: '', $this->patchTypes);
        $this->patchTypes += [
            'force' => null,
            'php' => null,
            'deprecations' => false,
        ];

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
    public function getClassLoader(): callable
    {
        return $this->classLoader;
    }

    /**
     * Wraps all autoloaders.
     */
    public static function enable(): void
    {
        // Ensures we don't hit https://bugs.php.net/42098
        class_exists(\Symfony\Component\ErrorHandler\ErrorHandler::class);
        class_exists(\Psr\Log\LogLevel::class);

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
    public static function disable(): void
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

    public static function checkClasses(): bool
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return false;
        }

        $loader = null;

        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof self) {
                $loader = $function[0];
                break;
            }
        }

        if (null === $loader) {
            return false;
        }

        static $offsets = [
            'get_declared_interfaces' => 0,
            'get_declared_traits' => 0,
            'get_declared_classes' => 0,
        ];

        foreach ($offsets as $getSymbols => $i) {
            $symbols = $getSymbols();

            for (; $i < \count($symbols); ++$i) {
                if (!is_subclass_of($symbols[$i], MockObject::class)
                    && !is_subclass_of($symbols[$i], ProphecySubjectInterface::class)
                    && !is_subclass_of($symbols[$i], Proxy::class)
                    && !is_subclass_of($symbols[$i], ProxyInterface::class)
                    && !is_subclass_of($symbols[$i], LegacyProxy::class)
                    && !is_subclass_of($symbols[$i], MockInterface::class)
                    && !is_subclass_of($symbols[$i], IMock::class)
                ) {
                    $loader->checkClass($symbols[$i]);
                }
            }

            $offsets[$getSymbols] = $i;
        }

        return true;
    }

    public function findFile(string $class): ?string
    {
        return $this->isFinder ? ($this->classLoader[0]->findFile($class) ?: null) : null;
    }

    /**
     * Loads the given class or interface.
     *
     * @throws \RuntimeException
     */
    public function loadClass(string $class): void
    {
        $e = error_reporting(error_reporting() | \E_PARSE | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR);

        try {
            if ($this->isFinder && !isset($this->loaded[$class])) {
                $this->loaded[$class] = true;
                if (!$file = $this->classLoader[0]->findFile($class) ?: '') {
                    // no-op
                } elseif (\function_exists('opcache_is_script_cached') && @opcache_is_script_cached($file)) {
                    include $file;

                    return;
                } elseif (false === include $file) {
                    return;
                }
            } else {
                ($this->classLoader)($class);
                $file = '';
            }
        } finally {
            error_reporting($e);
        }

        $this->checkClass($class, $file);
    }

    private function checkClass(string $class, string $file = null): void
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

    public function checkAnnotations(\ReflectionClass $refl, string $class): array
    {
        if (
            'Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV7' === $class
            || 'Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV6' === $class
            || 'Test\Symfony\Component\Debug\Tests' === $refl->getNamespaceName()
        ) {
            return [];
        }
        $deprecations = [];

        $className = false !== strpos($class, "@anonymous\0") ? (get_parent_class($class) ?: key(class_implements($class)) ?: 'class').'@anonymous' : $class;

        // Don't trigger deprecations for classes in the same vendor
        if ($class !== $className) {
            $vendor = preg_match('/^namespace ([^;\\\\\s]++)[;\\\\]/m', @file_get_contents($refl->getFileName()), $vendor) ? $vendor[1].'\\' : '';
            $vendorLen = \strlen($vendor);
        } elseif (2 > $vendorLen = 1 + (strpos($class, '\\') ?: strpos($class, '_'))) {
            $vendorLen = 0;
            $vendor = '';
        } else {
            $vendor = str_replace('_', '\\', substr($class, 0, $vendorLen));
        }

        // Detect annotations on the class
        if (false !== $doc = $refl->getDocComment()) {
            foreach (['final', 'deprecated', 'internal'] as $annotation) {
                if (false !== strpos($doc, $annotation) && preg_match('#\n\s+\* @'.$annotation.'(?:( .+?)\.?)?\r?\n\s+\*(?: @|/$|\r?\n)#s', $doc, $notice)) {
                    self::${$annotation}[$class] = isset($notice[1]) ? preg_replace('#\.?\r?\n( \*)? *(?= |\r?\n|$)#', '', $notice[1]) : '';
                }
            }

            if ($refl->isInterface() && false !== strpos($doc, 'method') && preg_match_all('#\n \* @method\s+(static\s+)?+([\w\|&\[\]\\\]+\s+)?(\w+(?:\s*\([^\)]*\))?)+(.+?([[:punct:]]\s*)?)?(?=\r?\n \*(?: @|/$|\r?\n))#', $doc, $notice, \PREG_SET_ORDER)) {
                foreach ($notice as $method) {
                    $static = '' !== $method[1] && !empty($method[2]);
                    $name = $method[3];
                    $description = $method[4] ?? null;
                    if (false === strpos($name, '(')) {
                        $name .= '()';
                    }
                    if (null !== $description) {
                        $description = trim($description);
                        if (!isset($method[5])) {
                            $description .= '.';
                        }
                    }
                    self::$method[$class][] = [$class, $name, $static, $description];
                }
            }
        }

        $parent = get_parent_class($class) ?: null;
        $parentAndOwnInterfaces = $this->getOwnInterfaces($class, $parent);
        if ($parent) {
            $parentAndOwnInterfaces[$parent] = $parent;

            if (!isset(self::$checkedClasses[$parent])) {
                $this->checkClass($parent);
            }

            if (isset(self::$final[$parent])) {
                $deprecations[] = sprintf('The "%s" class is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $parent, self::$final[$parent], $className);
            }
        }

        // Detect if the parent is annotated
        foreach ($parentAndOwnInterfaces + class_uses($class, false) as $use) {
            if (!isset(self::$checkedClasses[$use])) {
                $this->checkClass($use);
            }
            if (isset(self::$deprecated[$use]) && strncmp($vendor, str_replace('_', '\\', $use), $vendorLen) && !isset(self::$deprecated[$class])) {
                $type = class_exists($class, false) ? 'class' : (interface_exists($class, false) ? 'interface' : 'trait');
                $verb = class_exists($use, false) || interface_exists($class, false) ? 'extends' : (interface_exists($use, false) ? 'implements' : 'uses');

                $deprecations[] = sprintf('The "%s" %s %s "%s" that is deprecated%s.', $className, $type, $verb, $use, self::$deprecated[$use]);
            }
            if (isset(self::$internal[$use]) && strncmp($vendor, str_replace('_', '\\', $use), $vendorLen)) {
                $deprecations[] = sprintf('The "%s" %s is considered internal%s. It may change without further notice. You should not use it from "%s".', $use, class_exists($use, false) ? 'class' : (interface_exists($use, false) ? 'interface' : 'trait'), self::$internal[$use], $className);
            }
            if (isset(self::$method[$use])) {
                if ($refl->isAbstract()) {
                    if (isset(self::$method[$class])) {
                        self::$method[$class] = array_merge(self::$method[$class], self::$method[$use]);
                    } else {
                        self::$method[$class] = self::$method[$use];
                    }
                } elseif (!$refl->isInterface()) {
                    if (!strncmp($vendor, str_replace('_', '\\', $use), $vendorLen)
                        && 0 === strpos($className, 'Symfony\\')
                        && (!class_exists(InstalledVersions::class)
                            || 'symfony/symfony' !== InstalledVersions::getRootPackage()['name'])
                    ) {
                        // skip "same vendor" @method deprecations for Symfony\* classes unless symfony/symfony is being tested
                        continue;
                    }
                    $hasCall = $refl->hasMethod('__call');
                    $hasStaticCall = $refl->hasMethod('__callStatic');
                    foreach (self::$method[$use] as $method) {
                        [$interface, $name, $static, $description] = $method;
                        if ($static ? $hasStaticCall : $hasCall) {
                            continue;
                        }
                        $realName = substr($name, 0, strpos($name, '('));
                        if (!$refl->hasMethod($realName) || !($methodRefl = $refl->getMethod($realName))->isPublic() || ($static && !$methodRefl->isStatic()) || (!$static && $methodRefl->isStatic())) {
                            $deprecations[] = sprintf('Class "%s" should implement method "%s::%s"%s', $className, ($static ? 'static ' : '').$interface, $name, null == $description ? '.' : ': '.$description);
                        }
                    }
                }
            }
        }

        if (trait_exists($class)) {
            $file = $refl->getFileName();

            foreach ($refl->getMethods() as $method) {
                if ($method->getFileName() === $file) {
                    self::$methodTraits[$file][$method->getStartLine()] = $class;
                }
            }

            return $deprecations;
        }

        // Inherit @final, @internal, @param and @return annotations for methods
        self::$finalMethods[$class] = [];
        self::$internalMethods[$class] = [];
        self::$annotatedParameters[$class] = [];
        self::$returnTypes[$class] = [];
        foreach ($parentAndOwnInterfaces as $use) {
            foreach (['finalMethods', 'internalMethods', 'annotatedParameters', 'returnTypes'] as $property) {
                if (isset(self::${$property}[$use])) {
                    self::${$property}[$class] = self::${$property}[$class] ? self::${$property}[$use] + self::${$property}[$class] : self::${$property}[$use];
                }
            }

            if (null !== (self::INTERNAL_TYPES[$use] ?? null)) {
                foreach (self::INTERNAL_TYPES[$use] as $method => $returnType) {
                    if ('void' !== $returnType) {
                        self::$returnTypes[$class] += [$method => [$returnType, $returnType, $use, '']];
                    }
                }
            }
        }

        foreach ($refl->getMethods() as $method) {
            if ($method->class !== $class) {
                continue;
            }

            if (null === $ns = self::$methodTraits[$method->getFileName()][$method->getStartLine()] ?? null) {
                $ns = $vendor;
                $len = $vendorLen;
            } elseif (2 > $len = 1 + (strpos($ns, '\\') ?: strpos($ns, '_'))) {
                $len = 0;
                $ns = '';
            } else {
                $ns = str_replace('_', '\\', substr($ns, 0, $len));
            }

            if ($parent && isset(self::$finalMethods[$parent][$method->name])) {
                [$declaringClass, $message] = self::$finalMethods[$parent][$method->name];
                $deprecations[] = sprintf('The "%s::%s()" method is considered final%s. It may change without further notice as of its next major version. You should not extend it from "%s".', $declaringClass, $method->name, $message, $className);
            }

            if (isset(self::$internalMethods[$class][$method->name])) {
                [$declaringClass, $message] = self::$internalMethods[$class][$method->name];
                if (strncmp($ns, $declaringClass, $len)) {
                    $deprecations[] = sprintf('The "%s::%s()" method is considered internal%s. It may change without further notice. You should not extend it from "%s".', $declaringClass, $method->name, $message, $className);
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
                        $deprecations[] = sprintf($deprecation, $className);
                    }
                }
            }

            $forcePatchTypes = $this->patchTypes['force'];

            if ($canAddReturnType = null !== $forcePatchTypes && false === strpos($method->getFileName(), \DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR)) {
                if ('void' !== (self::MAGIC_METHODS[$method->name] ?? 'void')) {
                    $this->patchTypes['force'] = $forcePatchTypes ?: 'docblock';
                }

                $canAddReturnType = false !== strpos($refl->getFileName(), \DIRECTORY_SEPARATOR.'Tests'.\DIRECTORY_SEPARATOR)
                    || $refl->isFinal()
                    || $method->isFinal()
                    || $method->isPrivate()
                    || ('' === (self::$internal[$class] ?? null) && !$refl->isAbstract())
                    || '' === (self::$final[$class] ?? null)
                    || preg_match('/@(final|internal)$/m', $doc)
                ;
            }

            if (null !== ($returnType = self::$returnTypes[$class][$method->name] ?? self::MAGIC_METHODS[$method->name] ?? null) && !$method->hasReturnType() && !($doc && preg_match('/\n\s+\* @return +([^\s<(]+)/', $doc))) {
                [$normalizedType, $returnType, $declaringClass, $declaringFile] = \is_string($returnType) ? [$returnType, $returnType, '', ''] : $returnType;

                if ('void' === $normalizedType) {
                    $canAddReturnType = false;
                }

                if ($canAddReturnType && 'docblock' !== $this->patchTypes['force']) {
                    $this->patchMethod($method, $returnType, $declaringFile, $normalizedType);
                }

                if (false === strpos($doc, '* @deprecated') && strncmp($ns, $declaringClass, $len)) {
                    if ($canAddReturnType && 'docblock' === $this->patchTypes['force'] && false === strpos($method->getFileName(), \DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR)) {
                        $this->patchMethod($method, $returnType, $declaringFile, $normalizedType);
                    } elseif ('' !== $declaringClass && $this->patchTypes['deprecations']) {
                        $deprecations[] = sprintf('Method "%s::%s()" will return "%s" as of its next major version. Doing the same in %s "%s" will be required when upgrading.', $declaringClass, $method->name, $normalizedType, interface_exists($declaringClass) ? 'implementation' : 'child class', $className);
                    }
                }
            }

            if (!$doc) {
                $this->patchTypes['force'] = $forcePatchTypes;

                continue;
            }

            $matches = [];

            if (!$method->hasReturnType() && ((false !== strpos($doc, '@return') && preg_match('/\n\s+\* @return +([^\s<(]+)/', $doc, $matches)) || 'void' !== (self::MAGIC_METHODS[$method->name] ?? 'void'))) {
                $matches = $matches ?: [1 => self::MAGIC_METHODS[$method->name]];
                $this->setReturnType($matches[1], $method, $parent);

                if (isset(self::$returnTypes[$class][$method->name][0]) && $canAddReturnType) {
                    $this->fixReturnStatements($method, self::$returnTypes[$class][$method->name][0]);
                }

                if ($method->isPrivate()) {
                    unset(self::$returnTypes[$class][$method->name]);
                }
            }

            $this->patchTypes['force'] = $forcePatchTypes;

            if ($method->isPrivate()) {
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
            foreach ($matches as [, $parameterType, $parameterName]) {
                if (!isset($definedParameters[$parameterName])) {
                    $parameterType = trim($parameterType);
                    self::$annotatedParameters[$class][$method->name][$parameterName] = sprintf('The "%%s::%s()" method will require a new "%s$%s" argument in the next major version of its %s "%s", not defining it is deprecated.', $method->name, $parameterType ? $parameterType.' ' : '', $parameterName, interface_exists($className) ? 'interface' : 'parent class', $className);
                }
            }
        }

        return $deprecations;
    }

    public function checkCase(\ReflectionClass $refl, string $file, string $class): ?array
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
            return $real .= $dirFiles[$file];
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

        return $real .= $dirFiles[$kFile];
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

    private function setReturnType(string $types, \ReflectionMethod $method, ?string $parent): void
    {
        $nullable = false;
        $typesMap = [];
        foreach (explode('|', $types) as $t) {
            $typesMap[$this->normalizeType($t, $method->class, $parent)] = $t;
        }

        if (isset($typesMap['array'])) {
            if (isset($typesMap['Traversable']) || isset($typesMap['\Traversable'])) {
                $typesMap['iterable'] = 'array' !== $typesMap['array'] ? $typesMap['array'] : 'iterable';
                unset($typesMap['array'], $typesMap['Traversable'], $typesMap['\Traversable']);
            } elseif ('array' !== $typesMap['array'] && isset(self::$returnTypes[$method->class][$method->name])) {
                return;
            }
        }

        if (isset($typesMap['array']) && isset($typesMap['iterable'])) {
            if ('[]' === substr($typesMap['array'], -2)) {
                $typesMap['iterable'] = $typesMap['array'];
            }
            unset($typesMap['array']);
        }

        $iterable = $object = true;
        foreach ($typesMap as $n => $t) {
            if ('null' !== $n) {
                $iterable = $iterable && (\in_array($n, ['array', 'iterable']) || false !== strpos($n, 'Iterator'));
                $object = $object && (\in_array($n, ['callable', 'object', '$this', 'static']) || !isset(self::SPECIAL_RETURN_TYPES[$n]));
            }
        }

        $normalizedType = key($typesMap);
        $returnType = current($typesMap);

        foreach ($typesMap as $n => $t) {
            if ('null' === $n) {
                $nullable = true;
            } elseif ('null' === $normalizedType) {
                $normalizedType = $t;
                $returnType = $t;
            } elseif ($n !== $normalizedType || !preg_match('/^\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $n)) {
                if ($iterable) {
                    $normalizedType = $returnType = 'iterable';
                } elseif ($object && 'object' === $this->patchTypes['force']) {
                    $normalizedType = $returnType = 'object';
                } else {
                    // ignore multi-types return declarations
                    return;
                }
            }
        }

        if ('void' === $normalizedType || (\PHP_VERSION_ID >= 80000 && 'mixed' === $normalizedType)) {
            $nullable = false;
        } elseif (!isset(self::BUILTIN_RETURN_TYPES[$normalizedType]) && isset(self::SPECIAL_RETURN_TYPES[$normalizedType])) {
            // ignore other special return types
            return;
        }

        if ($nullable) {
            $normalizedType = '?'.$normalizedType;
            $returnType .= '|null';
        }

        self::$returnTypes[$method->class][$method->name] = [$normalizedType, $returnType, $method->class, $method->getFileName()];
    }

    private function normalizeType(string $type, string $class, ?string $parent): string
    {
        if (isset(self::SPECIAL_RETURN_TYPES[$lcType = strtolower($type)])) {
            if ('parent' === $lcType = self::SPECIAL_RETURN_TYPES[$lcType]) {
                $lcType = null !== $parent ? '\\'.$parent : 'parent';
            } elseif ('self' === $lcType) {
                $lcType = '\\'.$class;
            }

            return $lcType;
        }

        if ('[]' === substr($type, -2)) {
            return 'array';
        }

        if (preg_match('/^(array|iterable|callable) *[<(]/', $lcType, $m)) {
            return $m[1];
        }

        // We could resolve "use" statements to return the FQDN
        // but this would be too expensive for a runtime checker

        return $type;
    }

    /**
     * Utility method to add @return annotations to the Symfony code-base where it triggers a self-deprecations.
     */
    private function patchMethod(\ReflectionMethod $method, string $returnType, string $declaringFile, string $normalizedType)
    {
        static $patchedMethods = [];
        static $useStatements = [];

        if (!file_exists($file = $method->getFileName()) || isset($patchedMethods[$file][$startLine = $method->getStartLine()])) {
            return;
        }

        $patchedMethods[$file][$startLine] = true;
        $fileOffset = self::$fileOffsets[$file] ?? 0;
        $startLine += $fileOffset - 2;
        $nullable = '?' === $normalizedType[0] ? '?' : '';
        $normalizedType = ltrim($normalizedType, '?');
        $returnType = explode('|', $returnType);
        $code = file($file);

        foreach ($returnType as $i => $type) {
            if (preg_match('/((?:\[\])+)$/', $type, $m)) {
                $type = substr($type, 0, -\strlen($m[1]));
                $format = '%s'.$m[1];
            } elseif (preg_match('/^(array|iterable)<([^,>]++)>$/', $type, $m)) {
                $type = $m[2];
                $format = $m[1].'<%s>';
            } else {
                $format = null;
            }

            if (isset(self::SPECIAL_RETURN_TYPES[$type]) || ('\\' === $type[0] && !$p = strrpos($type, '\\', 1))) {
                continue;
            }

            [$namespace, $useOffset, $useMap] = $useStatements[$file] ?? $useStatements[$file] = self::getUseStatements($file);

            if ('\\' !== $type[0]) {
                [$declaringNamespace, , $declaringUseMap] = $useStatements[$declaringFile] ?? $useStatements[$declaringFile] = self::getUseStatements($declaringFile);

                $p = strpos($type, '\\', 1);
                $alias = $p ? substr($type, 0, $p) : $type;

                if (isset($declaringUseMap[$alias])) {
                    $type = '\\'.$declaringUseMap[$alias].($p ? substr($type, $p) : '');
                } else {
                    $type = '\\'.$declaringNamespace.$type;
                }

                $p = strrpos($type, '\\', 1);
            }

            $alias = substr($type, 1 + $p);
            $type = substr($type, 1);

            if (!isset($useMap[$alias]) && (class_exists($c = $namespace.$alias) || interface_exists($c) || trait_exists($c))) {
                $useMap[$alias] = $c;
            }

            if (!isset($useMap[$alias])) {
                $useStatements[$file][2][$alias] = $type;
                $code[$useOffset] = "use $type;\n".$code[$useOffset];
                ++$fileOffset;
            } elseif ($useMap[$alias] !== $type) {
                $alias .= 'FIXME';
                $useStatements[$file][2][$alias] = $type;
                $code[$useOffset] = "use $type as $alias;\n".$code[$useOffset];
                ++$fileOffset;
            }

            $returnType[$i] = null !== $format ? sprintf($format, $alias) : $alias;

            if (!isset(self::SPECIAL_RETURN_TYPES[$normalizedType]) && !isset(self::SPECIAL_RETURN_TYPES[$returnType[$i]])) {
                $normalizedType = $returnType[$i];
            }
        }

        if ('docblock' === $this->patchTypes['force'] || ('object' === $normalizedType && '7.1' === $this->patchTypes['php'])) {
            $returnType = implode('|', $returnType);

            if ($method->getDocComment()) {
                $code[$startLine] = "     * @return $returnType\n".$code[$startLine];
            } else {
                $code[$startLine] .= <<<EOTXT
    /**
     * @return $returnType
     */

EOTXT;
            }

            $fileOffset += substr_count($code[$startLine], "\n") - 1;
        }

        self::$fileOffsets[$file] = $fileOffset;
        file_put_contents($file, $code);

        $this->fixReturnStatements($method, $nullable.$normalizedType);
    }

    private static function getUseStatements(string $file): array
    {
        $namespace = '';
        $useMap = [];
        $useOffset = 0;

        if (!file_exists($file)) {
            return [$namespace, $useOffset, $useMap];
        }

        $file = file($file);

        for ($i = 0; $i < \count($file); ++$i) {
            if (preg_match('/^(class|interface|trait|abstract) /', $file[$i])) {
                break;
            }

            if (0 === strpos($file[$i], 'namespace ')) {
                $namespace = substr($file[$i], \strlen('namespace '), -2).'\\';
                $useOffset = $i + 2;
            }

            if (0 === strpos($file[$i], 'use ')) {
                $useOffset = $i;

                for (; 0 === strpos($file[$i], 'use '); ++$i) {
                    $u = explode(' as ', substr($file[$i], 4, -2), 2);

                    if (1 === \count($u)) {
                        $p = strrpos($u[0], '\\');
                        $useMap[substr($u[0], false !== $p ? 1 + $p : 0)] = $u[0];
                    } else {
                        $useMap[$u[1]] = $u[0];
                    }
                }

                break;
            }
        }

        return [$namespace, $useOffset, $useMap];
    }

    private function fixReturnStatements(\ReflectionMethod $method, string $returnType)
    {
        if ('7.1' === $this->patchTypes['php'] && 'object' === ltrim($returnType, '?') && 'docblock' !== $this->patchTypes['force']) {
            return;
        }

        if (!file_exists($file = $method->getFileName())) {
            return;
        }

        $fixedCode = $code = file($file);
        $i = (self::$fileOffsets[$file] ?? 0) + $method->getStartLine();

        if ('?' !== $returnType && 'docblock' !== $this->patchTypes['force']) {
            $fixedCode[$i - 1] = preg_replace('/\)(;?\n)/', "): $returnType\\1", $code[$i - 1]);
        }

        $end = $method->isGenerator() ? $i : $method->getEndLine();
        for (; $i < $end; ++$i) {
            if ('void' === $returnType) {
                $fixedCode[$i] = str_replace('    return null;', '    return;', $code[$i]);
            } elseif ('mixed' === $returnType || '?' === $returnType[0]) {
                $fixedCode[$i] = str_replace('    return;', '    return null;', $code[$i]);
            } else {
                $fixedCode[$i] = str_replace('    return;', "    return $returnType!?;", $code[$i]);
            }
        }

        if ($fixedCode !== $code) {
            file_put_contents($file, $fixedCode);
        }
    }
}
