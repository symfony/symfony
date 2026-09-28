<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;

/**
 * Builds the configuration trees of the extensions once for the passes that dump them.
 *
 * @internal
 */
final class ExtensionConfigTrees implements CompilerPassInterface
{
    private array $trees;

    /**
     * @param array<class-string, array<string, bool>> $bundlesDefinition
     */
    public function __construct(
        private array $bundlesDefinition,
    ) {
    }

    /**
     * @return list<array{string, ExtensionInterface, NodeInterface, array<string, bool>|null}> The alias, the extension, its tree and the environments of its bundle, or null when no bundle declares it
     */
    public function get(ContainerBuilder $container): array
    {
        if (isset($this->trees)) {
            return $this->trees;
        }

        $trees = [];
        $registeredExtensions = $container->getExtensions();
        foreach ($this->bundlesDefinition as $bundle => $envs) {
            if (!is_subclass_of($bundle, BundleInterface::class)) {
                continue;
            }

            if (!$extension = new $bundle()->getContainerExtension()) {
                continue;
            }

            $extensionAlias = $extension->getAlias();
            if (isset($registeredExtensions[$extensionAlias])) {
                $extension = $registeredExtensions[$extensionAlias];
                unset($registeredExtensions[$extensionAlias]);
            }

            if ($tree = $this->buildTree($extension, $container)) {
                $trees[] = [$extensionAlias, $extension, $tree, $envs];
            }
        }

        foreach ($registeredExtensions as $alias => $extension) {
            if ($tree = $this->buildTree($extension, $container)) {
                $trees[] = [$alias, $extension, $tree, null];
            }
        }

        return $this->trees = $trees;
    }

    /**
     * Releases the trees, once the passes that need them ran.
     */
    public function process(ContainerBuilder $container): void
    {
        unset($this->trees);
    }

    private function buildTree(ExtensionInterface $extension, ContainerBuilder $container): ?NodeInterface
    {
        $configuration = match (true) {
            $extension instanceof ConfigurationInterface => $extension,
            $extension instanceof ConfigurationExtensionInterface => $extension->getConfiguration([], $container),
            default => null,
        };

        if (!$configuration) {
            return null;
        }

        $tree = $configuration->getConfigTreeBuilder()->buildTree();

        if ($tree instanceof ArrayNode && !$tree instanceof PrototypedArrayNode && !$tree->getChildren()) {
            return null;
        }

        return $tree;
    }
}
