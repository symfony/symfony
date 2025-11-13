<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class StreamerDumper
{
    private ?Filesystem $fs = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $propertyMetadataLoader,
        private string $cacheDir,
        private ?ConfigCacheFactoryInterface $cacheFactory = null,
    ) {
    }

    /**
     * Dumps the generated content to the given path, optionally using config cache.
     *
     * @param callable(): string $generateContent
     */
    public function dump(Type $type, string $path, callable $generateContent): void
    {
        if ($this->cacheFactory) {
            $this->cacheFactory->cache(
                $path,
                function (ConfigCacheInterface $cache) use ($generateContent, $type) {
                    $resourceClasses = $this->getResourceClassNames($type);
                    $cache->write(
                        $generateContent(),
                        array_map(fn (string $c) => new ReflectionClassResource(new \ReflectionClass($c)), $resourceClasses),
                    );
                },
            );

            return;
        }

        $this->fs ??= new Filesystem();

        if (!$this->fs->exists($this->cacheDir)) {
            $this->fs->mkdir($this->cacheDir);
        }

        if (!$this->fs->exists($path)) {
            $this->fs->dumpFile($path, $generateContent());
        }
    }

    /**
     * Retrieves resources class names required for caching based on the provided type.
     *
     * @param list<class-string>   $classNames
     * @param array<string, mixed> $context
     *
     * @return list<class-string>
     */
    private function getResourceClassNames(Type $type, array $classNames = [], array $context = []): array
    {
        $context['original_type'] ??= $type;

        foreach ($type->traverse() as $t) {
            if ($t instanceof ObjectType) {
                if (\in_array($t->getClassName(), $classNames, true)) {
                    return $classNames;
                }

                $classNames[] = $t->getClassName();

                foreach ($this->propertyMetadataLoader->load($t->getClassName(), [], $context) as $property) {
                    $classNames = [...$classNames, ...$this->getResourceClassNames($property->getType(), $classNames)];
                }
            }

            if ($t instanceof GenericType) {
                foreach ($t->getVariableTypes() as $variableType) {
                    $classNames = [...$classNames, ...$this->getResourceClassNames($variableType, $classNames)];
                }
            }
        }

        return array_values(array_unique($classNames));
    }
}
