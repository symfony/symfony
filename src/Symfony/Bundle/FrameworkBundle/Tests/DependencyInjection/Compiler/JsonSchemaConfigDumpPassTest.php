<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\JsonSchemaConfigDumpPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Yaml\Schema\SchemaValidator;
use Symfony\Component\Yaml\Yaml;

#[RequiresMethod(JsonSchemaDumper::class, 'dump')]
#[RequiresMethod(Yaml::class, 'parse')]
class JsonSchemaConfigDumpPassTest extends TestCase
{
    private string $readOnlyDir;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/sf_test_json_schema';
        mkdir($this->tempDir, 0o777, true);

        $this->readOnlyDir = $this->tempDir.'/readonly';
        mkdir($this->readOnlyDir, 0o444, true);

        if ('\\' === \DIRECTORY_SEPARATOR) {
            exec('attrib +r '.escapeshellarg($this->readOnlyDir));
        }
    }

    protected function tearDown(): void
    {
        // tearDown() still runs when setUp() did not complete, leaving the properties unset
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            if ('\\' === \DIRECTORY_SEPARATOR) {
                exec('attrib -r '.escapeshellarg($this->readOnlyDir));
            }

            $fs = new Filesystem();
            $fs->remove($this->tempDir);
        }
    }

    public function testProcessGeneratesSchemaFile()
    {
        $container = new ContainerBuilder();

        $pass = new JsonSchemaConfigDumpPass($this->tempDir.'/schema.json', [
            JsonSchemaTestBundle::class => ['all' => true],
        ]);
        $pass->process($container);

        $schemaFile = $this->tempDir.'/schema.json';
        $this->assertFileExists($schemaFile);

        $schema = json_decode(file_get_contents($schemaFile), true);
        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('json_schema_test', $schema['$defs']['nodes']);
        $this->assertSame(['$ref' => '#/$defs/nodes/json_schema_test'], $schema['properties']['json_schema_test']);
        $this->assertArrayNotHasKey('when@all', $schema['properties']);
        // patternProperties allows any when@<custom-env> with 'all' bundles
        $this->assertSame(['$ref' => '#/$defs/nodes/json_schema_test'], $schema['patternProperties']['^when@[a-zA-Z0-9]+$']['properties']['json_schema_test']);
        $this->assertEquals([new FileResource(realpath($this->tempDir).'/schema.json')], $container->getResources());
    }

    public function testProcessCreatesWhenEnvProperties()
    {
        $container = new ContainerBuilder();

        $pass = new JsonSchemaConfigDumpPass($this->tempDir.'/schema_env.json', [
            JsonSchemaTestBundle::class => ['dev' => true, 'test' => true],
        ]);
        $pass->process($container);

        $schema = json_decode(file_get_contents($this->tempDir.'/schema_env.json'), true);
        $this->assertArrayNotHasKey('json_schema_test', $schema['properties']);
        $this->assertArrayHasKey('when@dev', $schema['properties']);
        $this->assertArrayHasKey('json_schema_test', $schema['properties']['when@dev']['properties']);
        $this->assertSame(['$ref' => '#/$defs/nodes/json_schema_test'], $schema['properties']['when@dev']['properties']['json_schema_test']);
        $this->assertArrayHasKey('when@test', $schema['properties']);
    }

    public function testProcessMixedEnvs()
    {
        $container = new ContainerBuilder();

        $pass = new JsonSchemaConfigDumpPass($this->tempDir.'/schema_mixed.json', [
            JsonSchemaTestBundle::class => ['all' => true],
            JsonSchemaDevBundle::class => ['dev' => true],
        ]);
        $pass->process($container);

        $schema = json_decode(file_get_contents($this->tempDir.'/schema_mixed.json'), true);
        // 'all' bundle in root properties
        $this->assertSame(['$ref' => '#/$defs/nodes/json_schema_test'], $schema['properties']['json_schema_test']);
        // dev-only bundle not in root properties
        $this->assertArrayNotHasKey('json_schema_dev', $schema['properties']);
        // when@dev contains both the 'all' bundle and the dev-only bundle, with additionalProperties: false
        $this->assertArrayHasKey('when@dev', $schema['properties']);
        $this->assertArrayHasKey('json_schema_test', $schema['properties']['when@dev']['properties']);
        $this->assertArrayHasKey('json_schema_dev', $schema['properties']['when@dev']['properties']);
        $this->assertFalse($schema['properties']['when@dev']['additionalProperties']);
        // when@dev must not be nested inside another when@
        $this->assertArrayNotHasKey('when@dev', $schema['properties']['when@dev']['properties']);
    }

    public function testProcessWhenEnvNotNested()
    {
        $container = new ContainerBuilder();

        $pass = new JsonSchemaConfigDumpPass($this->tempDir.'/schema_nested.json', [
            JsonSchemaTestBundle::class => ['all' => true],
            JsonSchemaDevBundle::class => ['dev' => true, 'test' => true],
        ]);
        $pass->process($container);

        $schema = json_decode(file_get_contents($this->tempDir.'/schema_nested.json'), true);
        // when@test must not contain when@dev
        $this->assertArrayNotHasKey('when@dev', $schema['properties']['when@test']['properties']);
        $this->assertArrayNotHasKey('when@test', $schema['properties']['when@dev']['properties']);
    }

    #[RequiresMethod(Validator::class, 'validate')]
    #[RequiresMethod(SchemaValidator::class, 'validate')]
    public function testGeneratedSchemaKeepsTheListFormOfNormalizedKeyedMaps()
    {
        $schemaFile = $this->tempDir.'/framework_schema.json';
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        (new JsonSchemaConfigDumpPass($schemaFile, [FrameworkBundle::class => ['all' => true]]))->process($container);

        // The asset-mapper recipe configures "paths" as a list, which a
        // normalization closure turns into a map keyed by path.
        $config = Yaml::parse(<<<YAML
            framework:
                asset_mapper:
                    paths:
                        - assets/
            YAML);

        $this->assertSame([], (new SchemaValidator())->validate($config, $schemaFile));
    }

    #[RequiresMethod(Validator::class, 'validate')]
    #[RequiresMethod(SchemaValidator::class, 'validate')]
    public function testGeneratedSchemaRejectsTheListFormOfPlainKeyedMaps()
    {
        $schemaFile = $this->tempDir.'/framework_schema.json';
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        (new JsonSchemaConfigDumpPass($schemaFile, [FrameworkBundle::class => ['all' => true]]))->process($container);

        // A list here would configure a context entry named "0" instead of "enable_max_depth".
        $config = Yaml::parse(<<<YAML
            framework:
                serializer:
                    default_context:
                        - enable_max_depth
            YAML);

        $this->assertNotSame([], (new SchemaValidator())->validate($config, $schemaFile));
    }

    public function testProcessIgnoresFileWriteErrors()
    {
        $container = new ContainerBuilder();

        $pass = new JsonSchemaConfigDumpPass($this->readOnlyDir.'/schema.json', [
            JsonSchemaTestBundle::class => ['all' => true],
        ]);
        $pass->process($container);

        $this->assertFileDoesNotExist($this->readOnlyDir.'/schema.json');
        $this->assertEmpty($container->getResources());
    }
}

class JsonSchemaTestBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new JsonSchemaTestExtension();
    }
}

class JsonSchemaTestExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'json_schema_test';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new JsonSchemaTestConfiguration();
    }
}

class JsonSchemaTestConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('json_schema_test');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('name')->end()
                ->booleanNode('enabled')->defaultFalse()->end()
            ->end();

        return $treeBuilder;
    }
}

class JsonSchemaDevBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new JsonSchemaDevExtension();
    }
}

class JsonSchemaDevExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'json_schema_dev';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new JsonSchemaDevConfiguration();
    }
}

class JsonSchemaDevConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('json_schema_dev');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('toolbar')->defaultTrue()->end()
            ->end();

        return $treeBuilder;
    }
}
