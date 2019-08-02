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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ProfilerPassTest extends TestCase
{
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
        $this->expectException('InvalidArgumentException');
        $builder = new ContainerBuilder();
        $builder->register('profiler', 'ProfilerClass');
        $builder->register('my_collector_service')
            ->addTag('data_collector', ['template' => 'foo']);

        $profilerPass = new ProfilerPass();
        $profilerPass->process($builder);
    }

    public function testValidCollector()
    {
        $container = new ContainerBuilder();
        $profilerDefinition = $container->register('profiler', 'ProfilerClass');
        $container->register('my_collector_service')
            ->addTag('data_collector', ['template' => 'foo', 'id' => 'my_collector']);

        $profilerPass = new ProfilerPass();
        $profilerPass->process($container);

        $this->assertSame(['my_collector_service' => ['my_collector', 'foo']], $container->getParameter('data_collector.templates'));

        // grab the method calls off of the "profiler" definition
        $methodCalls = $profilerDefinition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('add', $methodCalls[0][0]); // grab the method part of the first call
    }
}
