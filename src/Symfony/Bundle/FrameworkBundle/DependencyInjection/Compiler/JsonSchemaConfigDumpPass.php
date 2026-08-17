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
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
class JsonSchemaConfigDumpPass implements CompilerPassInterface
{
    /**
     * @param array<class-string, array<string, bool>> $bundlesDefinition
     */
    public function __construct(
        private string $schemaFile,
        private array $bundlesDefinition,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Yaml::class) || !class_exists(JsonSchemaDumper::class)) {
            return;
        }

        $generator = new JsonSchemaDumper(parameterSchemas: [['$ref' => '#/$defs/types/param']]);

        $defs = [];
        $allAliases = [];
        $envAliases = [];

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

            if (!$configuration = $this->getConfiguration($extension, $container)) {
                continue;
            }

            $tree = $configuration->getConfigTreeBuilder()->buildTree();
            if ($tree instanceof ArrayNode && !$tree instanceof PrototypedArrayNode && !$tree->getChildren()) {
                continue;
            }

            $defs[$extensionAlias] = $generator->dumpNode($tree);

            if ($envs['all'] ?? false) {
                $allAliases[] = $extensionAlias;
            }
            foreach ($envs as $env => $active) {
                if ($active && 'all' !== $env) {
                    $envAliases[$env][] = $extensionAlias;
                }
            }
        }

        foreach ($registeredExtensions as $alias => $extension) {
            if (!$configuration = $this->getConfiguration($extension, $container)) {
                continue;
            }

            $tree = $configuration->getConfigTreeBuilder()->buildTree();
            if ($tree instanceof ArrayNode && !$tree instanceof PrototypedArrayNode && !$tree->getChildren()) {
                continue;
            }

            $defs[$alias] = $generator->dumpNode($tree);
            $allAliases[] = $alias;
        }

        $allDefs = $generator->getAllDefs();
        $allDefs['types']['param'] = ['type' => 'string', 'pattern' => '^%[^%]+%$'];
        ksort($allDefs['types']);
        ksort($defs);
        $allDefs['nodes'] = $defs;
        $allProperties = [];
        foreach ($allAliases as $alias) {
            $allProperties[$alias] = ['$ref' => '#/$defs/nodes/'.$alias];
        }

        ksort($allProperties);
        ksort($envAliases);
        $rootProperties = $allProperties;
        foreach ($envAliases as $env => $aliases) {
            $whenProperties = $allProperties;
            foreach ($aliases as $alias) {
                $whenProperties[$alias] = ['$ref' => '#/$defs/nodes/'.$alias];
            }
            ksort($whenProperties);
            $rootProperties['when@'.$env] = [
                '$ref' => '#/$defs/types/object_null',
                'properties' => $whenProperties,
                'additionalProperties' => false,
            ];
        }

        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$comment' => 'This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.',
            '$defs' => $allDefs,
            'type' => 'object',
            'properties' => $rootProperties,
            'patternProperties' => [
                '^when@[a-zA-Z0-9]+$' => [
                    '$ref' => '#/$defs/types/object_null',
                    'properties' => $allProperties,
                ],
            ],
        ];

        $content = json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";

        $dir = \dirname($this->schemaFile);
        if (is_dir($dir) && is_writable($dir)) {
            if (!is_file($this->schemaFile) || file_get_contents($this->schemaFile) !== $content) {
                file_put_contents($this->schemaFile, $content);
            }

            $container->addResource(new FileResource($this->schemaFile));
        }
    }

    private function getConfiguration(ExtensionInterface $extension, ContainerBuilder $container): ?ConfigurationInterface
    {
        return match (true) {
            $extension instanceof ConfigurationInterface => $extension,
            $extension instanceof ConfigurationExtensionInterface => $extension->getConfiguration([], $container),
            default => null,
        };
    }
}
