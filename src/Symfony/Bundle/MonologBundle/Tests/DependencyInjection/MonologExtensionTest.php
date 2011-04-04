<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection;

use Symfony\Bundle\MonologBundle\Tests\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MonologExtensionTest extends TestCase
{
    public function testLoadWithDefault()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'stream')))), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('%kernel.logs_dir%/%kernel.environment%.log', \Monolog\Logger::DEBUG, false));
    }

    public function testLoadWithCustomValues()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'ERROR')))), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, true));
    }

    public function testLoadWithSeveralHandlers()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array(
            'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'ERROR'),
            'main' => array('type' => 'fingerscrossed', 'action_level' => 'ERROR', 'handler' => 'nested'),
            'nested' => array('type' => 'stream')
        ))), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, true));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.fingerscrossed.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), \Monolog\Logger::ERROR, 0, false));
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingDebugName()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('debug' => array('type' => 'stream')))), $container);
    }

    /**
     * Assertion on the Class of a DIC Service Definition.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition
     * @param string $expectedClass
     */
    protected function assertDICDefinitionClass($definition, $expectedClass)
    {
        $this->assertEquals($expectedClass, $definition->getClass(), "Expected Class of the DIC Container Service Definition is wrong.");
    }

    protected function assertDICConstructorArguments($definition, $args)
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '" . $definition->getClass()."' don't match.");
    }

    protected function assertDICDefinitionMethodCallAt($pos, $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        if (isset($calls[$pos][0])) {
            $this->assertEquals($methodName, $calls[$pos][0], "Method '".$methodName."' is expected to be called at position $pos.");

            if ($params !== null) {
                $this->assertEquals($params, $calls[$pos][1], "Expected parameters to methods '" . $methodName . "' do not match the actual parameters.");
            }
        }
    }
}
