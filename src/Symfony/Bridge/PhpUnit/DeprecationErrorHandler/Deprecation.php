<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

use Doctrine\Deprecations\Deprecation as DoctrineDeprecation;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Metadata\Api\Groups;
use PHPUnit\Util\Test;
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerFor;
use Symfony\Component\ErrorHandler\DebugClassLoader;

class_exists(Groups::class);

/**
 * @internal
 */
class Deprecation
{
    public const PATH_TYPE_VENDOR = 'path_type_vendor';
    public const PATH_TYPE_SELF = 'path_type_internal';
    public const PATH_TYPE_UNDETERMINED = 'path_type_undetermined';

    public const TYPE_SELF = 'type_self';
    public const TYPE_DIRECT = 'type_direct';
    public const TYPE_INDIRECT = 'type_indirect';
    public const TYPE_UNDETERMINED = 'type_undetermined';

    private $trace = [];
    private $message;
    private $languageDeprecation;
    private $originClass;
    private $originMethod;
    private $triggeringFile;
    private $triggeringClass;

    /** @var string[] Absolute paths to vendor directories */
    private static $vendors;

    /**
     * @var string[] Absolute paths to source or tests of the project, cache
     *               directories excluded because it is based on autoloading
     *               rules and cache systems typically do not use those
     */
    private static $internalPaths = [];

    private $originalFilesStack;

    public function __construct(string $message, array $trace, string $file, bool $languageDeprecation = false)
    {
        if (DebugClassLoader::class === ($trace[2]['class'] ?? '')) {
            $this->triggeringClass = $trace[2]['args'][0];
        }

        switch ($trace[2]['function'] ?? '') {
            case 'trigger_deprecation':
                $file = $trace[2]['file'];
                array_splice($trace, 1, 1);
                break;

            case 'delegateTriggerToBackend':
                if (DoctrineDeprecation::class === ($trace[2]['class'] ?? '')) {
                    $file = $trace[3]['file'];
                    array_splice($trace, 1, 2);
                }
                break;
        }

        $this->trace = $trace;
        $this->message = $message;
        $this->languageDeprecation = $languageDeprecation;

        $i = \count($trace);
        while (1 < $i && $this->lineShouldBeSkipped($trace[--$i])) {
            // No-op
        }

        $line = $trace[$i];
        $this->triggeringFile = $file;

        for ($j = 1; $j < $i; ++$j) {
            if (!isset($trace[$j]['function'], $trace[1 + $j]['class'], $trace[1 + $j]['args'][0])) {
                continue;
            }

            if ('trigger_error' === $trace[$j]['function'] && !isset($trace[$j]['class'])) {
                if (DebugClassLoader::class === $trace[1 + $j]['class']) {
                    $class = $trace[1 + $j]['args'][0];
                    $this->triggeringFile = isset($trace[1 + $j]['args'][1]) ? realpath($trace[1 + $j]['args'][1]) : (new \ReflectionClass($class))->getFileName();
                    $this->getOriginalFilesStack();
                    array_splice($this->originalFilesStack, 0, $j, [$this->triggeringFile]);

                    if (preg_match('/(?|"([^"]++)" that is deprecated|should implement method "(?:static )?([^:]++))/', $message, $m) || (false === strpos($message, '()" will return') && false === strpos($message, 'native return type declaration') && preg_match('/^(?:The|Method) "([^":]++)/', $message, $m))) {
                        $this->triggeringFile = (new \ReflectionClass($m[1]))->getFileName();
                        array_unshift($this->originalFilesStack, $this->triggeringFile);
                    }
                }

                break;
            }
        }

        if (!isset($line['object']) && !isset($line['class'])) {
            return;
        }

        set_error_handler(function () {});
        try {
            $parsedMsg = unserialize($this->message);
        } finally {
            restore_error_handler();
        }
        if ($parsedMsg && isset($parsedMsg['deprecation'])) {
            $this->message = $parsedMsg['deprecation'];
            $this->originClass = $parsedMsg['class'];
            $this->originMethod = $parsedMsg['method'];
            if (isset($parsedMsg['files_stack'])) {
                $this->originalFilesStack = $parsedMsg['files_stack'];
            }
            // If the deprecation has been triggered via
            // \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest()
            // then we need to use the serialized information to determine
            // if the error has been triggered from vendor code.
            if (isset($parsedMsg['triggering_file'])) {
                $this->triggeringFile = $parsedMsg['triggering_file'];
            }

            return;
        }

        if (!isset($line['class'], $trace[$i - 2]['function']) || 0 !== strpos($line['class'], SymfonyTestsListenerFor::class)) {
            $this->originClass = isset($line['object']) ? \get_class($line['object']) : $line['class'];
            $this->originMethod = $line['function'];

            return;
        }

        $test = $line['args'][0] ?? null;

        if (($test instanceof TestCase || $test instanceof TestSuite) && ('trigger_error' !== $trace[$i - 2]['function'] || isset($trace[$i - 2]['class']))) {
            $this->originClass = \get_class($test);
            $this->originMethod = $test->getName();

            return;
        }
    }

    private function lineShouldBeSkipped(array $line): bool
    {
        if (!isset($line['class'])) {
            return true;
        }
        $class = $line['class'];

        return 'ReflectionMethod' === $class || 0 === strpos($class, 'PHPUnit\\');
    }

    public function originatesFromDebugClassLoader(): bool
    {
        return isset($this->triggeringClass);
    }

    public function triggeringClass(): string
    {
        if (null === $this->triggeringClass) {
            throw new \LogicException('Check with originatesFromDebugClassLoader() before calling this method.');
        }

        return $this->triggeringClass;
    }

    public function originatesFromAnObject(): bool
    {
        return isset($this->originClass);
    }

    public function originatingClass(): string
    {
        if (null === $this->originClass) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }

        $class = $this->originClass;

        return false !== strpos($class, "@anonymous\0") ? (get_parent_class($class) ?: key(class_implements($class)) ?: 'class').'@anonymous' : $class;
    }

    public function originatingMethod(): string
    {
        if (null === $this->originMethod) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }

        return $this->originMethod;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isLegacy(): bool
    {
        if (!$this->originClass || (new \ReflectionClass($this->originClass))->isInternal()) {
            return false;
        }

        $method = $this->originatingMethod();
        $groups = class_exists(Groups::class, false) ? [new Groups(), 'groups'] : [Test::class, 'getGroups'];

        return 0 === strpos($method, 'testLegacy')
            || 0 === strpos($method, 'provideLegacy')
            || 0 === strpos($method, 'getLegacy')
            || strpos($this->originClass, '\Legacy')
            || \in_array('legacy', $groups($this->originClass, $method), true);
    }

    public function isMuted(): bool
    {
        if ('Function ReflectionType::__toString() is deprecated' !== $this->message) {
            return false;
        }
        if (isset($this->trace[1]['class'])) {
            return 0 === strpos($this->trace[1]['class'], 'PHPUnit\\');
        }

        return false !== strpos($this->triggeringFile, \DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'phpunit'.\DIRECTORY_SEPARATOR);
    }

    /**
     * Tells whether both the calling package and the called package are vendor
     * packages.
     */
    public function getType(): string
    {
        $pathType = $this->getPathType($this->triggeringFile);
        if ($this->languageDeprecation && self::PATH_TYPE_VENDOR === $pathType) {
            // the triggering file must be used for language deprecations
            return self::TYPE_INDIRECT;
        }
        if (self::PATH_TYPE_SELF === $pathType) {
            return self::TYPE_SELF;
        }
        if (self::PATH_TYPE_UNDETERMINED === $pathType) {
            return self::TYPE_UNDETERMINED;
        }
        $erroringFile = $erroringPackage = null;

        foreach ($this->getOriginalFilesStack() as $file) {
            if ('-' === $file || 'Standard input code' === $file || !realpath($file)) {
                continue;
            }
            if (self::PATH_TYPE_SELF === $pathType = $this->getPathType($file)) {
                return self::TYPE_DIRECT;
            }
            if (self::PATH_TYPE_UNDETERMINED === $pathType) {
                return self::TYPE_UNDETERMINED;
            }
            if (null !== $erroringFile && null !== $erroringPackage) {
                $package = $this->getPackage($file);
                if ('composer' !== $package && $package !== $erroringPackage) {
                    return self::TYPE_INDIRECT;
                }
                continue;
            }
            $erroringFile = $file;
            $erroringPackage = $this->getPackage($file);
        }

        return self::TYPE_DIRECT;
    }

    private function getOriginalFilesStack()
    {
        if (null === $this->originalFilesStack) {
            $this->originalFilesStack = [];
            foreach ($this->trace as $frame) {
                if (!isset($frame['file'], $frame['function']) || (!isset($frame['class']) && \in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true))) {
                    continue;
                }

                $this->originalFilesStack[] = $frame['file'];
            }
        }

        return $this->originalFilesStack;
    }

    /**
     * getPathType() should always be called prior to calling this method.
     */
    private function getPackage(string $path): string
    {
        $path = realpath($path) ?: $path;
        foreach (self::getVendors() as $vendorRoot) {
            if (0 === strpos($path, $vendorRoot)) {
                $relativePath = substr($path, \strlen($vendorRoot) + 1);
                $vendor = strstr($relativePath, \DIRECTORY_SEPARATOR, true);
                if (false === $vendor) {
                    return 'symfony';
                }

                return rtrim($vendor.'/'.strstr(substr($relativePath, \strlen($vendor) + 1), \DIRECTORY_SEPARATOR, true), '/');
            }
        }

        throw new \RuntimeException(sprintf('No vendors found for path "%s".', $path));
    }

    /**
     * @return string[]
     */
    private static function getVendors(): array
    {
        if (null === self::$vendors) {
            self::$vendors = $paths = [];
            self::$vendors[] = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Legacy';
            if (class_exists(DebugClassLoader::class, false)) {
                self::$vendors[] = \dirname((new \ReflectionClass(DebugClassLoader::class))->getFileName());
            }
            foreach (get_declared_classes() as $class) {
                if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                    $r = new \ReflectionClass($class);
                    $v = \dirname($r->getFileName(), 2);
                    if (file_exists($v.'/composer/installed.json')) {
                        self::$vendors[] = $v;
                        $loader = require $v.'/autoload.php';
                        $paths = self::addSourcePathsFromPrefixes(
                            array_merge($loader->getPrefixes(), $loader->getPrefixesPsr4()),
                            $paths
                        );
                    }
                }
            }
            foreach ($paths as $path) {
                foreach (self::$vendors as $vendor) {
                    if (0 !== strpos($path, $vendor)) {
                        self::$internalPaths[] = $path;
                    }
                }
            }
        }

        return self::$vendors;
    }

    private static function addSourcePathsFromPrefixes(array $prefixesByNamespace, array $paths): array
    {
        foreach ($prefixesByNamespace as $prefixes) {
            foreach ($prefixes as $prefix) {
                if (false !== realpath($prefix)) {
                    $paths[] = realpath($prefix);
                }
            }
        }

        return $paths;
    }

    private function getPathType(string $path): string
    {
        $realPath = realpath($path);
        if (false === $realPath && '-' !== $path && 'Standard input code' !== $path) {
            return self::PATH_TYPE_UNDETERMINED;
        }
        foreach (self::getVendors() as $vendor) {
            if (0 === strpos($realPath, $vendor) && false !== strpbrk(substr($realPath, \strlen($vendor), 1), '/'.\DIRECTORY_SEPARATOR)) {
                return self::PATH_TYPE_VENDOR;
            }
        }

        foreach (self::$internalPaths as $internalPath) {
            if (0 === strpos($realPath, $internalPath)) {
                return self::PATH_TYPE_SELF;
            }
        }

        return self::PATH_TYPE_UNDETERMINED;
    }

    public function toString(): string
    {
        $exception = new \Exception($this->message);
        $reflection = new \ReflectionProperty($exception, 'trace');
        $reflection->setAccessible(true);
        $reflection->setValue($exception, $this->trace);

        return ($this->originatesFromAnObject() ? 'deprecation triggered by '.$this->originatingClass().'::'.$this->originatingMethod().":\n" : '')
            .$this->message."\n"
            ."Stack trace:\n"
            .str_replace(' '.getcwd().\DIRECTORY_SEPARATOR, ' ', $exception->getTraceAsString())."\n";
    }
}
