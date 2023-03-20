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
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * Instantiates a new $className lazy ghost {@see \Symfony\Component\VarExporter\LazyGhostTrait}.
 *
 * The $className class must not final.
 *
 * A property must be a callable that returns the actual value when being called.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class LazyInstantiator
{
    private Filesystem $fs;

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
        private string $lazyGhostsDir,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                  $className
     * @param array<string, callable(): mixed> $propertiesCallables
     *
     * @return T
     */
    public function instantiate(string $className, array $propertiesCallables): object
    {
        $reflection = self::$cache['reflection'][$className] ??= new \ReflectionClass($className);
        $lazyClassName = self::$cache['lazy_class_name'][$className] ??= \sprintf('%sGhost', preg_replace('/\\\\/', '', $className));

        $initializer = function (object $object) use ($propertiesCallables) {
            foreach ($propertiesCallables as $name => $propertyCallable) {
                $object->{$name} = $propertyCallable();
            }
        };

        if (isset(self::$lazyClassesLoaded[$className]) && class_exists($lazyClassName)) {
            return $lazyClassName::createLazyGhost($initializer);
        }

        if (!is_file($path = \sprintf('%s%s%s.php', $this->lazyGhostsDir, \DIRECTORY_SEPARATOR, hash('xxh128', $className)))) {
            if (!$this->fs->exists($this->lazyGhostsDir)) {
                $this->fs->mkdir($this->lazyGhostsDir);
            }

            $lazyClassName = \sprintf('%sGhost', preg_replace('/\\\\/', '', $className));

            file_put_contents($path, \sprintf('<?php class %s%s', $lazyClassName, ProxyHelper::generateLazyGhost($reflection)));
        }

        require_once \sprintf('%s%s%s.php', $this->lazyGhostsDir, \DIRECTORY_SEPARATOR, hash('xxh128', $className));

        self::$lazyClassesLoaded[$className] = true;

        return $lazyClassName::createLazyGhost($initializer);
    }
}
