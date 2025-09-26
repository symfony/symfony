<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Builder;

use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class ConfigTraitsGenerator
{
    public function __construct(
        private string $outputDir,
    ) {
    }

    /**
     * @param array<string, ConfigurationInterface> $configurations Configurations indexed by their alias
     */
    public function build(array $configurations): \Closure
    {
        $paths = [];
        foreach ($configurations as $alias => $configuration) {
            if (trait_exists('\Symfony\Config\\'.$alias.'Trait')) {
                continue;
            }

            $class = new TraitBuilder('Symfony\Config', $alias);
            $class->addMethod($alias, <<<'PHP'
                /**
                 * @param COMMENT $config
                 */
                public function NAME(array $config): static
                {
                    $this->NAMEConfig->configure($config);

                    return $this;
                }
                PHP, [
                'COMMENT' => ArrayShapeGenerator::generate($configuration->getConfigTreeBuilder()->buildTree()),
                'NAME' => $alias,
            ]);

            $path = $this->getFullPath($class);
            file_put_contents($path, $class->build());

            $paths[] = $path;
        }

        return function () use ($paths) {
            foreach ($paths as $path) {
                require_once $path;
            }
        };
    }

    private function getFullPath(ClassBuilder|TraitBuilder $class): string
    {
        $directory = $this->outputDir.\DIRECTORY_SEPARATOR.$class->getDirectory();
        if (!is_dir($directory)) {
            @mkdir($directory, 0o777, true);
        }

        return $directory.\DIRECTORY_SEPARATOR.$class->getFilename();
    }
}
