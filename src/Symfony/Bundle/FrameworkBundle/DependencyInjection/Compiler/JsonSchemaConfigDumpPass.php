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

use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
class JsonSchemaConfigDumpPass implements CompilerPassInterface
{
    public function __construct(
        private string $schemaFile,
        private ExtensionConfigTrees $configTrees,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Yaml::class) || !class_exists(JsonSchemaDumper::class)) {
            return;
        }

        $trees = [];
        $allAliases = [];
        $envAliases = [];

        foreach ($this->configTrees->get($container) as [$alias, , $tree, $envs]) {
            $trees[$alias] = $tree;

            if (null === $envs || ($envs['all'] ?? false)) {
                $allAliases[] = $alias;
            }
            foreach ($envs ?? [] as $env => $active) {
                if ($active && 'all' !== $env) {
                    $envAliases[$env][] = $alias;
                }
            }
        }

        $generator = new JsonSchemaDumper([['$ref' => '#/$defs/types/param']], static fn (string $alias): ?string => isset($trees[$alias]) ? '#/$defs/nodes/'.$alias : null);

        $defs = [];
        foreach ($trees as $alias => $tree) {
            $defs[$alias] = $generator->dumpNode($tree);
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
}
