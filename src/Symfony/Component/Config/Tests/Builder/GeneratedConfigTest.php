<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Builder\ClassBuilder;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Builder\ConfigBuilderInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Tests\Builder\Fixtures\AddToList;
use Symfony\Component\Config\Tests\Builder\Fixtures\NodeInitialValues;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
use Symfony\Config\AddToListConfig;

/**
 * Test to use the generated config and test its output.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @covers \Symfony\Component\Config\Builder\ClassBuilder
 * @covers \Symfony\Component\Config\Builder\ConfigBuilderGenerator
 * @covers \Symfony\Component\Config\Builder\Method
 * @covers \Symfony\Component\Config\Builder\Property
 */
class GeneratedConfigTest extends TestCase
{
    private $tempDir = [];

    protected function setup(): void
    {
        parent::setup();

        $this->tempDir = [];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
        $this->tempDir = [];

        parent::tearDown();
    }

    public static function fixtureNames()
    {
        $array = [
            'ScalarNormalizedTypes' => 'scalar_normalized_types',
            'PrimitiveTypes' => 'primitive_types',
            'VariableType' => 'variable_type',
            'AddToList' => 'add_to_list',
            'NodeInitialValues' => 'node_initial_values',
            'ArrayExtraKeys' => 'array_extra_keys',
        ];

        foreach ($array as $name => $alias) {
            yield $name => [$name, $alias];
        }

        /*
         * Force load ContainerConfigurator to make env(), param() etc available
         * and also check if symfony/dependency-injection is installed
         */
        if (class_exists(ContainerConfigurator::class)) {
            yield 'Placeholders' => ['Placeholders', 'placeholders'];
        }
    }

    /**
     * @dataProvider fixtureNames
     */
    public function testConfig(string $name, string $alias)
    {
        $basePath = __DIR__.'/Fixtures/';
        $callback = include $basePath.$name.'.config.php';
        $expectedOutput = include $basePath.$name.'.output.php';
        $expectedCode = $basePath.$name;

        // to regenerate snapshot files, uncomment these lines
        // (new Filesystem())->remove($expectedCode);
        // $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name, $expectedCode);
        // $this->markTestIncomplete('Re-comment the line above and relaunch the tests');

        $outputDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('sf_config_builder', true);
        $configBuilder = $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name, $outputDir);
        $callback($configBuilder);

        $this->assertDirectorySame($expectedCode, $outputDir);

        $this->assertInstanceOf(ConfigBuilderInterface::class, $configBuilder);
        $this->assertSame($alias, $configBuilder->getExtensionAlias());
        $output = $configBuilder->toArray();
        if (class_exists(AbstractConfigurator::class)) {
            $output = AbstractConfigurator::processValue($output);
        }
        $this->assertSame($expectedOutput, $output);
    }

    /**
     * When you create a node, you can provide it with initial values. But the second
     * time you call a node, it is not created, hence you cannot give it initial values.
     */
    public function testSecondNodeWithInitialValuesThrowsException()
    {
        $configBuilder = $this->generateConfigBuilder(NodeInitialValues::class);
        $configBuilder->someCleverName(['second' => 'foo']);
        $this->expectException(InvalidConfigurationException::class);
        $configBuilder->someCleverName(['first' => 'bar']);
    }

    /**
     * When you create a named node, you can provide it with initial values. But
     * the second time you call a node, it is not created, hence you cannot give
     * it initial values.
     */
    public function testSecondNamedNodeWithInitialValuesThrowsException()
    {
        /** @var AddToListConfig $configBuilder */
        $configBuilder = $this->generateConfigBuilder(AddToList::class);
        $messenger = $configBuilder->messenger();
        $foo = $messenger->routing('foo', ['senders' => 'a']);
        $bar = $messenger->routing('bar', ['senders' => 'b']);
        $this->assertNotEquals($foo, $bar);

        $foo2 = $messenger->routing('foo');
        $this->assertEquals($foo, $foo2);

        $this->expectException(InvalidConfigurationException::class);
        $messenger->routing('foo', ['senders' => 'c']);
    }

    /**
     * Make sure you pass values that are defined.
     */
    public function testWrongInitialValues()
    {
        $configBuilder = $this->generateConfigBuilder(NodeInitialValues::class);
        $this->expectException(InvalidConfigurationException::class);
        $configBuilder->someCleverName(['not_exists' => 'foo']);
    }

    public function testSetExtraKeyMethodIsNotGeneratedWhenAllowExtraKeysIsFalse()
    {
        /** @var AddToListConfig $configBuilder */
        $configBuilder = $this->generateConfigBuilder(AddToList::class);

        $this->assertFalse(method_exists($configBuilder->translator(), 'set'));
        $this->assertFalse(method_exists($configBuilder->messenger()->receiving(), 'set'));
    }

    /**
     * Generate the ConfigBuilder or return an already generated instance.
     */
    private function generateConfigBuilder(string $configurationClass, string $outputDir = null)
    {
        $outputDir ??= sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('sf_config_builder', true);
        if (!str_contains($outputDir, __DIR__)) {
            $this->tempDir[] = $outputDir;
        }

        $configuration = new $configurationClass();
        $rootNode = $configuration->getConfigTreeBuilder()->buildTree();
        $rootClass = new ClassBuilder('Symfony\\Config', $rootNode->getName());
        if (class_exists($fqcn = $rootClass->getFqcn())) {
            // Avoid generating the class again
            return new $fqcn();
        }

        $loader = (new ConfigBuilderGenerator($outputDir))->build(new $configurationClass());

        return $loader();
    }

    private function assertDirectorySame($expected, $current)
    {
        $expectedFiles = [];
        foreach (new \RecursiveIteratorIterator(new RecursiveDirectoryIterator($expected, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isDir()) {
                continue;
            }
            $expectedFiles[substr($file->getPathname(), \strlen($expected))] = $file->getPathname();
        }
        $currentFiles = [];
        foreach (new \RecursiveIteratorIterator(new RecursiveDirectoryIterator($current, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isDir()) {
                continue;
            }
            $currentFiles[substr($file->getPathname(), \strlen($current))] = $file->getPathname();
        }

        $this->assertSame(array_keys($expectedFiles), array_keys($currentFiles));
        foreach ($expectedFiles as $fileName => $filePath) {
            $this->assertFileEquals($filePath, $currentFiles[$fileName]);
        }
    }
}
