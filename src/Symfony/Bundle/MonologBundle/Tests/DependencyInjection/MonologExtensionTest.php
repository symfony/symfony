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
use Symfony\Component\DependencyInjection\Definition;

class MonologExtensionTest extends TestCase
{
    public function testLoadWithDefault()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'stream')))), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('%kernel.logs_dir%/%kernel.environment%.log', \Monolog\Logger::DEBUG, true));
    }

    public function testLoadWithCustomValues()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR')))), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false));
    }

    public function testLoadWithSeveralHandlers()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array(
            'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR'),
            'main' => array('type' => 'fingers_crossed', 'action_level' => 'ERROR', 'handler' => 'nested'),
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
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::ERROR, false));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.fingers_crossed.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), \Monolog\Logger::ERROR, 0, true, true));
    }

    public function testLoadWithOverwriting()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'ERROR'),
                'main' => array('type' => 'fingers_crossed', 'action_level' => 'ERROR', 'handler' => 'nested'),
                'nested' => array('type' => 'stream')
            )),
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'WARNING'),
            ))
        ), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.custom');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/symfony.log', \Monolog\Logger::WARNING, true));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.fingers_crossed.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), \Monolog\Logger::ERROR, 0, true, true));
    }

    public function testLoadWithNewAtEnd()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'ERROR'),
                'main' => array('type' => 'fingers_crossed', 'action_level' => 'ERROR', 'handler' => 'nested'),
                'nested' => array('type' => 'stream')
            )),
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => false, 'level' => 'WARNING'),
                'new' => array('type' => 'stream', 'path' => '/tmp/monolog.log', 'bubble' => true, 'level' => 'ERROR'),
            ))
        ), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));
        $this->assertTrue($container->hasDefinition('monolog.handler.new'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.new')));
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));

        $handler = $container->getDefinition('monolog.handler.new');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/monolog.log', \Monolog\Logger::ERROR, true));
    }

    public function testLoadWithNewAndPriority()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'ERROR'),
                'main' => array('type' => 'buffer', 'level' => 'INFO', 'handler' => 'nested'),
                'nested' => array('type' => 'stream')
            )),
            array('handlers' => array(
                'custom' => array('type' => 'stream', 'path' => '/tmp/symfony.log', 'bubble' => true, 'level' => 'WARNING'),
                'first' => array('type' => 'rotating_file', 'path' => '/tmp/monolog.log', 'bubble' => true, 'level' => 'ERROR', 'priority' => 3),
                'last' => array('type' => 'stream', 'path' => '/tmp/last.log', 'bubble' => true, 'level' => 'ERROR', 'priority' => -3),
            ))
        ), $container);
        $this->assertTrue($container->hasDefinition('monolog.logger'));
        $this->assertTrue($container->hasDefinition('monolog.handler.custom'));
        $this->assertTrue($container->hasDefinition('monolog.handler.main'));
        $this->assertTrue($container->hasDefinition('monolog.handler.nested'));
        $this->assertTrue($container->hasDefinition('monolog.handler.first'));
        $this->assertTrue($container->hasDefinition('monolog.handler.last'));

        $logger = $container->getDefinition('monolog.logger');
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.last')));
        $this->assertDICDefinitionMethodCallAt(1, $logger, 'pushHandler', array(new Reference('monolog.handler.custom')));
        $this->assertDICDefinitionMethodCallAt(0, $logger, 'pushHandler', array(new Reference('monolog.handler.main')));
        $this->assertDICDefinitionMethodCallAt(2, $logger, 'pushHandler', array(new Reference('monolog.handler.first')));

        $handler = $container->getDefinition('monolog.handler.main');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.buffer.class%');
        $this->assertDICConstructorArguments($handler, array(new Reference('monolog.handler.nested'), 0, \Monolog\Logger::INFO, true));

        $handler = $container->getDefinition('monolog.handler.first');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.rotating_file.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/monolog.log', 0, \Monolog\Logger::ERROR, true));

        $handler = $container->getDefinition('monolog.handler.last');
        $this->assertDICDefinitionClass($handler, '%monolog.handler.stream.class%');
        $this->assertDICConstructorArguments($handler, array('/tmp/last.log', \Monolog\Logger::ERROR, true));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionWhenInvalidHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'invalid_handler')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingFingerscrossedWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'fingers_crossed')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingBufferWithoutHandler()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'buffer')))), $container);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionWhenUsingServiceWithoutId()
    {
        $container = new ContainerBuilder();
        $loader = new MonologExtension();

        $loader->load(array(array('handlers' => array('main' => array('type' => 'service')))), $container);
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
     * @param Definition $definition
     * @param string     $expectedClass
     */
    protected function assertDICDefinitionClass($definition, $expectedClass)
    {
        $this->assertEquals($expectedClass, $definition->getClass(), "Expected Class of the DIC Container Service Definition is wrong.");
    }

    protected function assertDICConstructorArguments($definition, $args)
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '".$definition->getClass()."' don't match.");
    }

    protected function assertDICDefinitionMethodCallAt($pos, $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        if (isset($calls[$pos][0])) {
            $this->assertEquals($methodName, $calls[$pos][0], "Method '".$methodName."' is expected to be called at position $pos.");

            if ($params !== null) {
                $this->assertEquals($params, $calls[$pos][1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
            }
        }
    }
}
