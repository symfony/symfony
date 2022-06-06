<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\CacheWarmer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryCompiler;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class CompiledClassMetadataCacheWarmer implements CacheWarmerInterface
{
    private $classesToCompile;

    private $classMetadataFactory;

    private $classMetadataFactoryCompiler;

    private $filesystem;

    public function __construct(array $classesToCompile, ClassMetadataFactoryInterface $classMetadataFactory, ClassMetadataFactoryCompiler $classMetadataFactoryCompiler, Filesystem $filesystem)
    {
        $this->classesToCompile = $classesToCompile;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->classMetadataFactoryCompiler = $classMetadataFactoryCompiler;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir): array
    {
        $metadatas = [];

        foreach ($this->classesToCompile as $classToCompile) {
            $metadatas[] = $this->classMetadataFactory->getMetadataFor($classToCompile);
        }

        $code = $this->classMetadataFactoryCompiler->compile($metadatas);

        $this->filesystem->dumpFile("{$cacheDir}/serializer.class.metadata.php", $code);

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional(): bool
    {
        return true;
    }
}
