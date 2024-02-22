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
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Builder\ConfigBuilderGeneratorInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generate all config builders.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ConfigBuilderCacheWarmer implements CacheWarmerInterface
{
    private KernelInterface $kernel;
    private ?LoggerInterface $logger;

    public function __construct(KernelInterface $kernel, ?LoggerInterface $logger = null)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$buildDir) {
            return [];
        }

        $generator = new ConfigBuilderGenerator($buildDir);

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

        foreach ($extensions as $extension) {
            try {
                $this->dumpExtension($extension, $generator);
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to generate ConfigBuilder for extension {extensionClass}: '.$e->getMessage(), ['exception' => $e, 'extensionClass' => $extension::class]);
            }
        }

        // No need to preload anything
        return [];
    }

    private function dumpExtension(ExtensionInterface $extension, ConfigBuilderGeneratorInterface $generator): void
    {
        $configuration = null;
        if ($extension instanceof ConfigurationInterface) {
            $configuration = $extension;
        } elseif ($extension instanceof ConfigurationExtensionInterface) {
            $container = $this->kernel->getContainer();
            $configuration = $extension->getConfiguration([], new ContainerBuilder($container instanceof Container ? new ContainerBag($container) : new ParameterBag()));
        }

        if (!$configuration) {
            return;
        }

        $generator->build($configuration);
    }

    public function isOptional(): bool
    {
        return false;
    }
}
