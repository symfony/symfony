<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DependencyInjection;

use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Serializer\Attribute\Serializable;
use Symfony\Component\Serializer\Builder\DefinitionExtractor;
use Symfony\Component\Serializer\Builder\NormalizerBuilder;

/**
 * Create custom normalizers and denormalizers. This class is used to glue things
 * together with FrameworkBundle.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 */
class CustomNormalizerHelper
{
    public function __construct(
        private NormalizerBuilder $builder,
        private DefinitionExtractor $definitionExtractor,
        private array $paths,
        private string $projectDir,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function build(string $outputDir): iterable
    {
        foreach ($this->paths as $prefix => $inputPath) {
            $path = $this->projectDir.\DIRECTORY_SEPARATOR.$inputPath;
            if (!is_dir($path)) {
                $this->logger?->error(sprintf('Path "%s" is not a directory', $path));
                continue;
            }

            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
                ->name('/\.php$/');

            foreach ($finder as $file) {
                $classNs = $this->getClassName($prefix, $file);
                if (!class_exists($classNs)) {
                    $this->logger?->warning(sprintf('Failed to guess class name for file "%s"', $file->getRealPath()));
                    continue;
                }

                $reflectionClass = new \ReflectionClass($classNs);
                if ([] === $reflectionClass->getAttributes(Serializable::class)) {
                    continue;
                }

                $classDefinition = $this->definitionExtractor->getDefinition($classNs);
                yield $this->builder->build($classDefinition, $outputDir);
            }
        }
    }

    /**
     * @return class-string
     */
    private function getClassName(string $prefix, SplFileInfo $file): string
    {
        $namespace = rtrim(sprintf('%s\\%s', $prefix, $file->getRelativePath()), '\\');

        return sprintf('%s\\%s', $namespace, $file->getFilenameWithoutExtension());
    }
}
