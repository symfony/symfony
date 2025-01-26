<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\JsonSchemaGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generate config json schema.
 */
final readonly class ConfigSchemaCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$buildDir || !$this->kernel->isDebug() || !class_exists(JsonSchemaGenerator::class)) {
            return [];
        }

        $generator = new JsonSchemaGenerator(\dirname($this->kernel->getBuildDir()).'/config.schema.json');

        if ($this->kernel instanceof Kernel) {
            /** @var ContainerBuilder $container */
            $container = \Closure::bind(function (Kernel $kernel) {
                $containerBuilder = $kernel->getContainerBuilder();
                $kernel->prepareContainer($containerBuilder);

                return $containerBuilder;
            }, null, $this->kernel)($this->kernel);

            $extensions = $container->getExtensions();
        } else {
            $extensions = [];
            foreach ($this->kernel->getBundles() as $bundle) {
                $extension = $bundle->getContainerExtension();
                if (null !== $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        $tree = new ArrayNode($this->kernel->getEnvironment());
        foreach ($extensions as $extension) {
            try {
                $configuration = null;
                if ($extension instanceof ConfigurationInterface) {
                    $configuration = $extension;
                } elseif ($extension instanceof ConfigurationExtensionInterface) {
                    $container = $this->kernel->getContainer();
                    $configuration = $extension->getConfiguration([], new ContainerBuilder($container instanceof Container ? new ContainerBag($container) : new ParameterBag()));
                }

                if (!$extensionConfigNode = $configuration?->getConfigTreeBuilder()->buildTree()) {
                    continue;
                }

                $tree->addChild($extensionConfigNode);
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to generate JSON schema for extension {extensionClass}: '.$e->getMessage(), ['exception' => $e, 'extensionClass' => $extension::class]);
            }
        }

        try {
            $generator->merge($tree, (object) [
                'description' => 'Symfony configuration',
                'properties' => (object) [
                    'when@'.$tree->getName() => ['$ref' => '#/definitions/'.$tree->getName()],
                ],
                'patternProperties' => (object) [
                    'when@[a-zA-Z0-9]+' => ['$ref' => '#/'],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger?->warning('Failed to generate JSON schema for the configuration: '.$e->getMessage(), ['exception' => $e]);
        }

        // No need to preload anything
        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }
}
