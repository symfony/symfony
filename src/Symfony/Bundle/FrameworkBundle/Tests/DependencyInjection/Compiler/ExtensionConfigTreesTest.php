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

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ExtensionConfigTrees;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\JsonSchemaConfigDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\JsonSchemaDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ExtensionConfigTreesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/sf_test_extension_config_trees';
        mkdir($this->tempDir, 0o777, true);
        CountingConfiguration::$builds = 0;
        CountingConfiguration::$tree = null;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[RequiresMethod(JsonSchemaDumper::class, 'dump')]
    #[RequiresMethod(Yaml::class, 'parse')]
    public function testDumpPassesBuildEachTreeOnce()
    {
        $container = new ContainerBuilder();
        $container->setParameter('.container.known_envs', ['dev']);
        $container->registerExtension(new CountingExtension());
        $configTrees = new ExtensionConfigTrees([]);

        (new PhpConfigReferenceDumpPass($this->tempDir.'/reference.php', $configTrees))->process($container);
        (new JsonSchemaConfigDumpPass($this->tempDir.'/schema.json', $configTrees))->process($container);

        $this->assertStringContainsString('counting?: CountingConfig,', file_get_contents($this->tempDir.'/reference.php'));
        $this->assertArrayHasKey('counting', json_decode(file_get_contents($this->tempDir.'/schema.json'), true)['properties']);
        $this->assertSame(1, CountingConfiguration::$builds);
    }

    #[RequiresMethod(JsonSchemaDumper::class, 'dump')]
    #[RequiresMethod(Yaml::class, 'parse')]
    public function testTreesAreReleasedOnceBothDumpPassesRan()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('.kernel.config_dir', $this->tempDir);
        $container->setParameter('.kernel.bundles_definition', []);
        $container->setParameter('.container.known_envs', ['dev']);
        $container->registerExtension(new CountingExtension());
        (new FrameworkBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $i = array_key_first(array_filter($passes, static fn ($pass) => $pass instanceof PhpConfigReferenceDumpPass));
        [$phpPass, $jsonPass, $releasePass] = \array_slice($passes, $i, 3);
        $this->assertInstanceOf(JsonSchemaConfigDumpPass::class, $jsonPass);
        $this->assertInstanceOf(ExtensionConfigTrees::class, $releasePass);

        $phpPass->process($container);
        $jsonPass->process($container);
        gc_collect_cycles();
        $this->assertNotNull(CountingConfiguration::$tree->get());

        $releasePass->process($container);
        gc_collect_cycles();
        $this->assertNull(CountingConfiguration::$tree->get());
    }
}

class CountingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'counting';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new CountingConfiguration();
    }
}

class CountingConfiguration implements ConfigurationInterface
{
    public static int $builds = 0;
    public static ?\WeakReference $tree = null;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        ++self::$builds;

        $treeBuilder = new TreeBuilder('counting');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('name')->end()
            ->end();
        self::$tree = \WeakReference::create($treeBuilder->buildTree());

        return $treeBuilder;
    }
}
