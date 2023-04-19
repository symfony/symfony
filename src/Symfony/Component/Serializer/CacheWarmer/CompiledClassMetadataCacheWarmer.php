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
    public function __construct(
        private readonly array $classesToCompile,
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
        private readonly ClassMetadataFactoryCompiler $classMetadataFactoryCompiler,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function warmUp(string $cacheDir): array
    {
        $metadatas = [];

        foreach ($this->classesToCompile as $classToCompile) {
            $metadatas[] = $this->classMetadataFactory->getMetadataFor($classToCompile);
        }

        $code = $this->classMetadataFactoryCompiler->compile($metadatas);

        $this->filesystem->dumpFile("{$cacheDir}/serializer.class.metadata.php", $code);

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }
}
