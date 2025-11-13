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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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
 */
#[IgnoreDeprecations]
#[Group('legacy')]
class GeneratedConfigTest extends TestCase
{
    private array $tempDir = [];

    protected function setup(): void
    {
        $this->tempDir = [];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
        $this->tempDir = [];
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
            'ArrayValues' => 'array_values',
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

    #[DataProvider('fixtureNames')]
    public function testConfig(string $name, string $alias)
    {
        $basePath = __DIR__.'/Fixtures/';
        $expectedOutput = include $basePath.$name.'.output.php';
        $expectedCode = $basePath.$name;

        if ($_ENV['TEST_GENERATE_FIXTURES'] ?? false) {
            (new Filesystem())->remove($expectedCode);
            $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name, $expectedCode);
            $this->markTestIncomplete('TEST_GENERATE_FIXTURES is set');
        }

        $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name, $outputDir);

        $config = include $basePath.$name.'.config.php';

        $this->assertDirectorySame($expectedCode, $outputDir);

        $this->assertInstanceOf(ConfigBuilderInterface::class, $config);
        $this->assertSame($alias, $config->getExtensionAlias());
        $output = $config->toArray();
        if (class_exists(AbstractConfigurator::class)) {
            $output = AbstractConfigurator::processValue($output);
        }
        $this->assertSame($expectedOutput, $output);
    }

    #[DataProvider('fixtureNames')]
    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testLegacyConfig(string $name, string $alias)
    {
        $basePath = __DIR__.'/Fixtures/';
        $callback = include $basePath.$name.'.legacy.php';
        $expectedOutput = include $basePath.$name.'.output.php';
        $expectedCode = $basePath.$name;

        $configBuilder = $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name, $outputDir);

        $this->expectUserDeprecationMessageMatches('{^Since symfony/config 7.4: Calling any fluent method on "Symfony\\\\Config\\\\.*Config" is deprecated; pass the configuration to the constructor instead\.}');
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
    private function generateConfigBuilder(string $configurationClass, ?string &$outputDir = null)
    {
        if (null === $outputDir) {
            $outputDir = tempnam(sys_get_temp_dir(), 'sf_config_builder_');
            unlink($outputDir);
            mkdir($outputDir);
            $this->tempDir[] = $outputDir;
        }

        $configuration = new $configurationClass();
        $rootNode = $configuration->getConfigTreeBuilder()->buildTree();
        $rootClass = new ClassBuilder('Symfony\\Config', $rootNode->getName(), $rootNode);
        $fqcn = $rootClass->getFqcn();

        $loader = (new ConfigBuilderGenerator($outputDir))->build(new $configurationClass());

        return class_exists($fqcn) ? new $fqcn() : $loader();
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
        ksort($expectedFiles);
        ksort($currentFiles);

        $this->assertSame(array_keys($expectedFiles), array_keys($currentFiles));
        foreach ($expectedFiles as $fileName => $filePath) {
            $this->assertFileEquals($filePath, $currentFiles[$fileName]);
        }
    }
}
