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
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

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
        $this->expectException(\InvalidArgumentException::class);
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

    public static function provideValidCollectorWithTemplateUsingAutoconfigure(): \Generator
    {
        yield [new class() implements TemplateAwareDataCollectorInterface {
            public function collect(Request $request, Response $response, \Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return static::class;
            }

            public function reset(): void
            {
            }

            public static function getTemplate(): string
            {
                return 'foo';
            }
        }];

        yield [new class() extends AbstractDataCollector {
            public function collect(Request $request, Response $response, \Throwable $exception = null): void
            {
            }

            public static function getTemplate(): string
            {
                return 'foo';
            }
        }];
    }

    /**
     * @dataProvider provideValidCollectorWithTemplateUsingAutoconfigure
     */
    public function testValidCollectorWithTemplateUsingAutoconfigure(TemplateAwareDataCollectorInterface $dataCollector)
    {
        $container = new ContainerBuilder();
        $profilerDefinition = $container->register('profiler', 'ProfilerClass');

        $container->registerForAutoconfiguration(DataCollectorInterface::class)->addTag('data_collector');
        $container->register('mydatacollector', $dataCollector::class)->setAutoconfigured(true);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ProfilerPass())->process($container);

        $idForTemplate = $dataCollector::class;
        $this->assertSame(['mydatacollector' => [$idForTemplate, 'foo']], $container->getParameter('data_collector.templates'));

        // grab the method calls off of the "profiler" definition
        $methodCalls = $profilerDefinition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('add', $methodCalls[0][0]); // grab the method part of the first call

        (new ResolveChildDefinitionsPass())->process($container);
        $this->assertSame($idForTemplate, $container->get('mydatacollector')->getName());
    }
}
