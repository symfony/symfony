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
                        array_map(static fn (string $c) => new ReflectionClassResource(new \ReflectionClass($c)), $resourceClasses),
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
     * Retrieves resource class names required for caching based on the provided type.
     *
     * @param array<class-string, class-string> $classNames
     * @param array<string, mixed>              $context
     *
     * @return array<class-string, class-string>
     */
    private function getResourceClassNames(Type $type, array $classNames = [], array $context = []): array
    {
        $context['original_type'] ??= $type;

        foreach ($type->traverse() as $t) {
            if ($t instanceof ObjectType) {
                if (isset($classNames[$className = $t->getClassName()])) {
                    continue;
                }

                $classNames[$className] = $className;

                foreach ($this->propertyMetadataLoader->load($className, [], $context) as $property) {
                    $classNames += $this->getResourceClassNames($property->getType(), $classNames, $context);
                }
            }

            if ($t instanceof GenericType) {
                foreach ($t->getVariableTypes() as $variableType) {
                    $classNames += $this->getResourceClassNames($variableType, $classNames, $context);
                }
            }
        }

        return $classNames;
    }
}
