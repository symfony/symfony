<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * ClassExistenceResource represents a class existence.
 * Freshness is only evaluated against resource existence.
 *
 * The resource must be a fully-qualified class name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ClassExistenceResource implements SelfCheckingResourceInterface
{
    private string $resource;
    private ?array $exists = null;

    private static int $autoloadLevel = 0;
    private static ?string $autoloadedClass = null;
    private static array $existsCache = [];

    /**
     * @param string    $resource The fully-qualified class name
     * @param bool|null $exists   Boolean when the existence check has already been done
     */
    public function __construct(string $resource, bool $exists = null)
    {
        $this->resource = $resource;
        if (null !== $exists) {
            $this->exists = [$exists, null];
        }
    }

    public function __toString(): string
    {
        return $this->resource;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * @throws \ReflectionException when a parent class/interface/trait is not found
     */
    public function isFresh(int $timestamp): bool
    {
        $loaded = class_exists($this->resource, false) || interface_exists($this->resource, false) || trait_exists($this->resource, false);

        if (null !== $exists = &self::$existsCache[$this->resource]) {
            if ($loaded) {
                $exists = [true, null];
            } elseif (0 >= $timestamp && !$exists[0] && null !== $exists[1]) {
                throw new \ReflectionException($exists[1]);
            }
        } elseif ([false, null] === $exists = [$loaded, null]) {
            if (!self::$autoloadLevel++) {
                spl_autoload_register(__CLASS__.'::throwOnRequiredClass');
            }
            $autoloadedClass = self::$autoloadedClass;
            self::$autoloadedClass = ltrim($this->resource, '\\');

            try {
                $exists[0] = class_exists($this->resource) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
            } catch (\Exception $e) {
                $exists[1] = $e->getMessage();

                try {
                    self::throwOnRequiredClass($this->resource, $e);
                } catch (\ReflectionException $e) {
                    if (0 >= $timestamp) {
                        throw $e;
                    }
                }
            } catch (\Throwable $e) {
                $exists[1] = $e->getMessage();

                throw $e;
            } finally {
                self::$autoloadedClass = $autoloadedClass;
                if (!--self::$autoloadLevel) {
                    spl_autoload_unregister(__CLASS__.'::throwOnRequiredClass');
                }
            }
        }

        $this->exists ??= $exists;

        return $this->exists[0] xor !$exists[0];
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        if (null === $this->exists) {
            $this->isFresh(0);
        }

        return ['resource', 'exists'];
    }

    /**
     * @internal
     */
    public function __wakeup(): void
    {
        if (\is_bool($this->exists)) {
            $this->exists = [$this->exists, null];
        }
    }

    /**
     * Throws a reflection exception when the passed class does not exist but is required.
     *
     * A class is considered "not required" when it's loaded as part of a "class_exists" or similar check.
     *
     * This function can be used as an autoload function to throw a reflection
     * exception if the class was not found by previous autoload functions.
     *
     * A previous exception can be passed. In this case, the class is considered as being
     * required totally, so if it doesn't exist, a reflection exception is always thrown.
     * If it exists, the previous exception is rethrown.
     *
     * @throws \ReflectionException
     *
     * @internal
     */
    public static function throwOnRequiredClass(string $class, \Exception $previous = null): void
    {
        // If the passed class is the resource being checked, we shouldn't throw.
        if (null === $previous && self::$autoloadedClass === $class) {
            return;
        }

        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            if (null !== $previous) {
                throw $previous;
            }

            return;
        }

        if ($previous instanceof \ReflectionException) {
            throw $previous;
        }

        $message = sprintf('Class "%s" not found.', $class);

        if ($class !== (self::$autoloadedClass ?? $class)) {
            $message = substr_replace($message, sprintf(' while loading "%s"', self::$autoloadedClass), -1, 0);
        }

        if (null !== $previous) {
            $message = $previous->getMessage();
        }

        $e = new \ReflectionException($message, 0, $previous);

        if (null !== $previous) {
            throw $e;
        }

        $trace = debug_backtrace();
        $autoloadFrame = [
            'function' => 'spl_autoload_call',
            'args' => [$class],
        ];

        if (isset($trace[1])) {
            $callerFrame = $trace[1];
            $i = 2;
        } elseif (false !== $i = array_search($autoloadFrame, $trace, true)) {
            $callerFrame = $trace[++$i];
        } else {
            throw $e;
        }

        if (isset($callerFrame['function']) && !isset($callerFrame['class'])) {
            switch ($callerFrame['function']) {
                case 'get_class_methods':
                case 'get_class_vars':
                case 'get_parent_class':
                case 'is_a':
                case 'is_subclass_of':
                case 'class_exists':
                case 'class_implements':
                case 'class_parents':
                case 'trait_exists':
                case 'defined':
                case 'interface_exists':
                case 'method_exists':
                case 'property_exists':
                case 'is_callable':
                    return;
            }

            $props = [
                'file' => $callerFrame['file'] ?? null,
                'line' => $callerFrame['line'] ?? null,
                'trace' => \array_slice($trace, 1 + $i),
            ];

            foreach ($props as $p => $v) {
                if (null !== $v) {
                    $r = new \ReflectionProperty(\Exception::class, $p);
                    $r->setValue($e, $v);
                }
            }
        }

        throw $e;
    }
}
