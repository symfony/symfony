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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;

class ProfilerPassTest extends TestCase
{
    private $profilerDefinition;

    protected function setUp()
    {
        $this->profilerDefinition = new Definition('ProfilerClass');
    }

    /**
     * Tests that collectors that specify a template but no "id" will throw
     * an exception (both are needed if the template is specified).
     *
     * Thus, a fully-valid tag looks something like this:
     *
     *     <tag name="data_collector" template="YourBundle:Collector:templatename" id="your_collector_name" />
     */
    public function testTemplateNoIdThrowsException()
    {
        // one service, with a template key, but no id
        $services = array(
            'my_collector_service' => array(0 => array('template' => 'foo')),
        );

        $builder = $this->createContainerMock($services);

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');

        $profilerPass = new ProfilerPass();
        $profilerPass->process($builder);
    }

    public function testValidCollector()
    {
        // one service, with a template key, but no id
        $services = array(
            'my_collector_service' => array(0 => array('template' => 'foo', 'id' => 'my_collector')),
        );

        $container = $this->createContainerMock($services);

        // fake the getDefinition() to return a Profiler definition
        $container->expects($this->atLeastOnce())
            ->method('getDefinition');

        // assert that the data_collector.templates parameter should be set
        $container->expects($this->once())
            ->method('setParameter')
            ->with('data_collector.templates', array('my_collector_service' => array('my_collector', 'foo')));

        $profilerPass = new ProfilerPass();
        $profilerPass->process($container);

        // grab the method calls off of the "profiler" definition
        $methodCalls = $this->profilerDefinition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('add', $methodCalls[0][0]); // grab the method part of the first call
    }

    private function createContainerMock($services)
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'getDefinition', 'findTaggedServiceIds', 'setParameter'))->getMock();
        $container->expects($this->any())
            ->method('hasDefinition')
            ->with($this->equalTo('profiler'))
            ->will($this->returnValue(true));
        $container->expects($this->any())
            ->method('getDefinition')
            ->will($this->returnValue($this->profilerDefinition));
        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        return $container;
    }
}
