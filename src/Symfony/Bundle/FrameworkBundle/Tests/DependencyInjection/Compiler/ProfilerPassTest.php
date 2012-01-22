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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;

class ProfilerPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests that collectors that specify a template but no "id" will throw
     * an exception (both are needed if the template is specified). Thus,
     * a fully-valid tag looks something like this:
     *
     *     <tag name="data_collector" template="YourBundle:Collector:templatename" id="your_collector_name" />
     */
    public function testTemplateNoIdThrowsException()
    {
        // one service, with a template key, but no id
        $services = array(
            'my_collector_service' => array(0 => array('template' => 'foo')),
        );

        $builder = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $this->setExpectedException('InvalidArgumentException');

        $profilerPass = new ProfilerPass();
        $profilerPass->process($builder);
    }

    public function testValidCollector()
    {
        // one service, with a template key, but no id
        $services = array(
            'my_collector_service' => array(0 => array('template' => 'foo', 'id' => 'my_collector')),
        );

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        // fake the getDefinition() to return a Profiler definition
        $definition = new Definition('ProfilerClass');
        $container->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        // assert that the data_collector.templates parameter should be set
        $container->expects($this->once())
            ->method('setParameter')
            ->with('data_collector.templates', array('my_collector_service' => array('my_collector', 'foo')));

        $profilerPass = new ProfilerPass();
        $profilerPass->process($container);

        // grab the method calls off of the "profiler" definition
        $methodCalls = $definition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('add', $methodCalls[0][0]); // grab the method part of the first call
    }
}
