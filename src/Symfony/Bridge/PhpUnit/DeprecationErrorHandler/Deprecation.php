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

use PHPUnit\Util\Test;
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerFor;
use Symfony\Component\Debug\DebugClassLoader as LegacyDebugClassLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * @internal
 */
class Deprecation
{
    const PATH_TYPE_VENDOR = 'path_type_vendor';
    const PATH_TYPE_SELF = 'path_type_internal';
    const PATH_TYPE_UNDETERMINED = 'path_type_undetermined';

    const TYPE_SELF = 'type_self';
    const TYPE_DIRECT = 'type_direct';
    const TYPE_INDIRECT = 'type_indirect';
    const TYPE_UNDETERMINED = 'type_undetermined';

    private $trace = [];
    private $message;
    private $originClass;
    private $originMethod;
    private $triggeringFile;

    /** @var string[] Absolute paths to vendor directories */
    private static $vendors;

    /**
     * @var string[] Absolute paths to source or tests of the project, cache
     *               directories excluded because it is based on autoloading
     *               rules and cache systems typically do not use those
     */
    private static $internalPaths = [];

    private $originalFilesStack;

    /**
     * @param string $message
     * @param string $file
     */
    public function __construct($message, array $trace, $file)
    {
        if (isset($trace[2]['function']) && 'trigger_deprecation' === $trace[2]['function']) {
            $file = $trace[2]['file'];
            array_splice($trace, 1, 1);
        }

        $this->trace = $trace;

        if ('trigger_error' === (isset($trace[1]['function']) ? $trace[1]['function'] : null)
            && (DebugClassLoader::class === ($class = (isset($trace[2]['class']) ? $trace[2]['class'] : null)) || LegacyDebugClassLoader::class === $class)
            && 'checkClass' === (isset($trace[2]['function']) ? $trace[2]['function'] : null)
            && null !== ($extraFile = (isset($trace[2]['args'][1]) ? $trace[2]['args'][1] : null))
            && '' !== $extraFile
            && false !== $extraFile = realpath($extraFile)
        ) {
            $this->getOriginalFilesStack();
            array_splice($this->originalFilesStack, 2, 1, $extraFile);
        }

        $this->message = $message;
        $i = \count($this->trace);
        while (1 < $i && $this->lineShouldBeSkipped($this->trace[--$i])) {
            // No-op
        }
        $line = $this->trace[$i];
        $this->triggeringFile = $file;
        if (isset($line['object']) || isset($line['class'])) {
            set_error_handler(function () {});
            $parsedMsg = unserialize($this->message);
            restore_error_handler();
            if ($parsedMsg && isset($parsedMsg['deprecation'])) {
                $this->message = $parsedMsg['deprecation'];
                $this->originClass = $parsedMsg['class'];
                $this->originMethod = $parsedMsg['method'];
                $this->originalFilesStack = $parsedMsg['files_stack'];
                // If the deprecation has been triggered via
                // \Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait::endTest()
                // then we need to use the serialized information to determine
                // if the error has been triggered from vendor code.
                if (isset($parsedMsg['triggering_file'])) {
                    $this->triggeringFile = $parsedMsg['triggering_file'];
                }

                return;
            }

            if (isset($line['class']) && 0 === strpos($line['class'], SymfonyTestsListenerFor::class)) {
                return;
            }

            $this->originClass = isset($line['object']) ? \get_class($line['object']) : $line['class'];
            $this->originMethod = $line['function'];
        }
    }

    /**
     * @return bool
     */
    private function lineShouldBeSkipped(array $line)
    {
        if (!isset($line['class'])) {
            return true;
        }
        $class = $line['class'];

        return 'ReflectionMethod' === $class || 0 === strpos($class, 'PHPUnit_') || 0 === strpos($class, 'PHPUnit\\');
    }

    /**
     * @return bool
     */
    public function originatesFromAnObject()
    {
        return isset($this->originClass);
    }

    /**
     * @return string
     */
    public function originatingClass()
    {
        if (null === $this->originClass) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }

        return $this->originClass;
    }

    /**
     * @return string
     */
    public function originatingMethod()
    {
        if (null === $this->originMethod) {
            throw new \LogicException('Check with originatesFromAnObject() before calling this method.');
        }

        return $this->originMethod;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    public function isLegacy()
    {
        $class = $this->originatingClass();
        if ((new \ReflectionClass($class))->isInternal()) {
            return false;
        }

        $method = $this->originatingMethod();

        return 0 === strpos($method, 'testLegacy')
            || 0 === strpos($method, 'provideLegacy')
            || 0 === strpos($method, 'getLegacy')
            || strpos($class, '\Legacy')
            || \in_array('legacy', Test::getGroups($class, $method), true);
    }

    /**
     * @return bool
     */
    public function isMuted()
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
     *
     * @return string
     */
    public function getType()
    {
        $triggeringFilePathType = $this->getPathType($this->triggeringFile);
        if (self::PATH_TYPE_SELF === $triggeringFilePathType) {
            return self::TYPE_SELF;
        }
        if (self::PATH_TYPE_UNDETERMINED === $triggeringFilePathType) {
            return self::TYPE_UNDETERMINED;
        }
        $erroringFile = $erroringPackage = null;

        foreach ($this->getOriginalFilesStack() as $file) {
            if ('-' === $file || 'Standard input code' === $file || !realpath($file)) {
                continue;
            }
            if (self::PATH_TYPE_SELF === $this->getPathType($file)) {
                return self::TYPE_DIRECT;
            }
            if (self::PATH_TYPE_UNDETERMINED === $this->getPathType($file)) {
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
                if (!isset($frame['file']) || \in_array($frame['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
                    continue;
                }

                $this->originalFilesStack[] = $frame['file'];
            }
        }

        return $this->originalFilesStack;
    }

    /**
     * getPathType() should always be called prior to calling this method.
     *
     * @param string $path
     *
     * @return string
     */
    private function getPackage($path)
    {
        $path = realpath($path) ?: $path;
        foreach (self::getVendors() as $vendorRoot) {
            if (0 === strpos($path, $vendorRoot)) {
                $relativePath = substr($path, \strlen($vendorRoot) + 1);
                $vendor = strstr($relativePath, \DIRECTORY_SEPARATOR, true);
                if (false === $vendor) {
                    throw new \RuntimeException(sprintf('Could not find directory separator "%s" in path "%s".', \DIRECTORY_SEPARATOR, $relativePath));
                }

                return rtrim($vendor.'/'.strstr(substr(
                    $relativePath,
                    \strlen($vendor) + 1
                ), \DIRECTORY_SEPARATOR, true), '/');
            }
        }

        throw new \RuntimeException(sprintf('No vendors found for path "%s".', $path));
    }

    /**
     * @return string[] an array of paths
     */
    private static function getVendors()
    {
        if (null === self::$vendors) {
            self::$vendors = $paths = [];
            foreach (get_declared_classes() as $class) {
                if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                    $r = new \ReflectionClass($class);
                    $v = \dirname(\dirname($r->getFileName()));
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

    private static function addSourcePathsFromPrefixes(array $prefixesByNamespace, array $paths)
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

    /**
     * @param string $path
     *
     * @return string
     */
    private function getPathType($path)
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

    /**
     * @return string
     */
    public function toString()
    {
        $exception = new \Exception($this->message);
        $reflection = new \ReflectionProperty($exception, 'trace');
        $reflection->setAccessible(true);
        $reflection->setValue($exception, $this->trace);

        return 'deprecation triggered by '.$this->originatingClass().'::'.$this->originatingMethod().':'.
        "\n".$this->message.
        "\nStack trace:".
        "\n".str_replace(' '.getcwd().\DIRECTORY_SEPARATOR, ' ', $exception->getTraceAsString()).
        "\n";
    }
}
