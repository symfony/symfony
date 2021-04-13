<?php

namespace Symfony\Component\Config\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Builder\ClassBuilder;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Builder\ConfigBuilderInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Tests\Builder\Fixtures\AddToList;
use Symfony\Component\Config\Tests\Builder\Fixtures\NodeInitialValues;
use Symfony\Config\AddToListConfig;

/**
 * Test to use the generated config and test its output.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class GeneratedConfigTest extends TestCase
{
    public function fixtureNames()
    {
        $array = [
            'PrimitiveTypes' => 'primitive_types',
            'VariableType' => 'variable_type',
            'AddToList' => 'add_to_list',
            'NodeInitialValues' => 'node_initial_values',
        ];

        foreach ($array as $name => $alias) {
            yield $name => [$name, $alias];
        }
    }

    /**
     * @dataProvider fixtureNames
     */
    public function testConfig(string $name, string $alias)
    {
        $basePath = __DIR__.'/Fixtures/';
        $configBuilder = $this->generateConfigBuilder('Symfony\\Component\\Config\\Tests\\Builder\\Fixtures\\'.$name);
        $callback = include $basePath.$name.'.config.php';
        $expectedOutput = include $basePath.$name.'.output.php';
        $callback($configBuilder);

        $this->assertInstanceOf(ConfigBuilderInterface::class, $configBuilder);
        $this->assertSame($alias, $configBuilder->getExtensionAlias());
        $this->assertSame($expectedOutput, $configBuilder->toArray());
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

    /**
     * Generate the ConfigBuilder or return an already generated instance.
     */
    private function generateConfigBuilder(string $configurationClass)
    {
        $configuration = new $configurationClass();
        $rootNode = $configuration->getConfigTreeBuilder()->buildTree();
        $rootClass = new ClassBuilder('Symfony\\Config', $rootNode->getName());
        if (class_exists($fqcn = $rootClass->getFqcn())) {
            // Avoid generating the class again
            return new $fqcn();
        }

        $outputDir = sys_get_temp_dir();
        // This line is helpful for debugging
        // $outputDir = __DIR__.'/.build';

        $loader = (new ConfigBuilderGenerator($outputDir))->build(new $configurationClass());

        return $loader();
    }
}
