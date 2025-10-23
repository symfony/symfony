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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PhpConfigReferenceDumpPassTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/sf_test_config_reference';
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            // Make the directory is writable on Windows
            if ('\\' === DIRECTORY_SEPARATOR && !is_writable($this->tempDir)) {
                exec('attrib -r ' . escapeshellarg($this->tempDir));
            }

            $fs = new Filesystem();
            $fs->remove($this->tempDir);
        }
    }

    public function testProcessWithConfigDir()
    {
        $container = new ContainerBuilder();
        $container->setParameter('.container.known_envs', ['test', 'dev']);

        $pass = new PhpConfigReferenceDumpPass($this->tempDir.'/reference.php', [
            TestBundle::class => ['all' => true],
        ]);
        $pass->process($container);

        $referenceFile = $this->tempDir.'/reference.php';
        $this->assertFileExists($referenceFile);

        $content = file_get_contents($referenceFile);
        $this->assertStringContainsString('namespace Symfony\Component\DependencyInjection\Loader\Configurator;', $content);
        $this->assertStringContainsString('final class App extends AppReference', $content);
        $this->assertStringContainsString('public static function config(array $config): array', $content);
    }

    public function testProcessIgnoresFileWriteErrors()
    {
        // Create a read-only directory to simulate write errors
        $readOnlyDir = $this->tempDir.'/readonly';
        mkdir($readOnlyDir, 0o444, true);

        // Make the directory read-only on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            exec('attrib +r ' . escapeshellarg($readOnlyDir));
        }

        $container = new ContainerBuilder();
        $container->setParameter('.container.known_envs', ['dev', 'prod', 'test']);

        $pass = new PhpConfigReferenceDumpPass($readOnlyDir.'/reference.php', [
            TestBundle::class => ['all' => true],
        ]);

        $pass->process($container);
        $this->assertFileDoesNotExist($readOnlyDir.'/reference.php');
    }

    public function testProcessGeneratesExpectedReferenceFile()
    {
        $container = new ContainerBuilder();
        $container->setParameter('.container.known_envs', ['dev', 'prod', 'test']);

        $extension = new TestExtension();
        $container->registerExtension($extension);

        $pass = new PhpConfigReferenceDumpPass($this->tempDir.'/reference.php', [
            TestBundle::class => ['all' => true],
        ]);
        $pass->process($container);

        if ($_ENV['TEST_GENERATE_FIXTURES'] ?? false) {
            copy($this->tempDir.'/reference.php', __DIR__.'/../../Fixtures/reference.php');
            self::markTestIncomplete('TEST_GENERATE_FIXTURES is set');
        }

        $this->assertFileEquals(__DIR__.'/../../Fixtures/reference.php', $this->tempDir.'/reference.php');
    }
}

class TestBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new TestExtension();
    }
}

class TestExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getNamespace(): string
    {
        return 'test';
    }

    public function getXsdValidationBasePath(): string
    {
        return '';
    }

    public function getAlias(): string
    {
        return 'test';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new TestConfiguration();
    }
}

class TestConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('test');
        $rootNode = $treeBuilder->getRootNode();

        if ($rootNode instanceof ArrayNodeDefinition) {
            $rootNode
                ->children()
                    ->scalarNode('enabled')->defaultFalse()->end()
                    ->arrayNode('options')
                        ->children()
                            ->scalarNode('name')->end()
                            ->integerNode('count')->end()
                        ->end()
                    ->end()
                ->end();
        }

        return $treeBuilder;
    }
}
