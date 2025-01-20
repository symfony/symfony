<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * Instantiates a new $className lazy ghost {@see \Symfony\Component\VarExporter\LazyGhostTrait}.
 *
 * Prior to PHP 8.4, the "$className" argument class must not be final.
 * The $initializer must be a callable that sets the actual object values when being called.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class LazyInstantiator
{
    private ?Filesystem $fs = null;

    /**
     * @var array{reflection: array<class-string, \ReflectionClass<object>>, lazy_class_name: array<class-string, class-string>}
     */
    private static array $cache = [
        'reflection' => [],
        'lazy_class_name' => [],
    ];

    /**
     * @var array<class-string, true>
     */
    private static array $lazyClassesLoaded = [];

    public function __construct(
        private ?string $lazyGhostsDir = null,
    ) {
        if (null === $this->lazyGhostsDir && \PHP_VERSION_ID < 80400) {
            throw new InvalidArgumentException('The "$lazyGhostsDir" argument cannot be null when using PHP < 8.4.');
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>   $className
     * @param callable(T): void $initializer
     *
     * @return T
     */
    public function instantiate(string $className, callable $initializer): object
    {
        try {
            $classReflection = self::$cache['reflection'][$className] ??= new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        // use native lazy ghosts if available
        if (\PHP_VERSION_ID >= 80400) {
            return $classReflection->newLazyGhost($initializer);
        }

        $this->fs ??= new Filesystem();

        $lazyClassName = self::$cache['lazy_class_name'][$className] ??= \sprintf('%sGhost', preg_replace('/\\\\/', '', $className));

        if (isset(self::$lazyClassesLoaded[$className]) && class_exists($lazyClassName)) {
            return $lazyClassName::createLazyGhost($initializer);
        }

        if (!is_file($path = \sprintf('%s%s%s.php', $this->lazyGhostsDir, \DIRECTORY_SEPARATOR, hash('xxh128', $className)))) {
            if (!$this->fs->exists($this->lazyGhostsDir)) {
                $this->fs->mkdir($this->lazyGhostsDir);
            }

            file_put_contents($path, \sprintf('<?php class %s%s', $lazyClassName, ProxyHelper::generateLazyGhost($classReflection)));
        }

        require_once $path;

        self::$lazyClassesLoaded[$className] = true;

        return $lazyClassName::createLazyGhost($initializer);
    }
}
