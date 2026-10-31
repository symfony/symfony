<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\DataCollector\RateLimiterDataCollector;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterBuilder;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterFactory;
use Symfony\Component\RateLimiter\DependencyInjection\RateLimiterTracePass;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterBuilderInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimiterTracingTest extends TestCase
{
    public function testBuilderAndFactoryOperationsAreRecordedWithoutChangingResults()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);

        $factory = $builder->fixedWindow('api', 2, '1 minute');
        $limiter = $factory->create('secret-key');
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume(2)->isAccepted());
        $limiter->reset();
        $this->assertTrue($limiter->consume()->isAccepted());

        $operations = $this->operations($collector, $request);
        $this->assertSame(['consume', 'consume', 'reset', 'consume'], array_column($operations, 'operation'));
        $this->assertSame([true, false, null, true], array_column($operations, 'accepted'));
        $this->assertSame('api', $operations[0]['limiter']);
        $this->assertSame('fixed_window', $operations[0]['policy']);
        $this->assertSame(['secret-key', 'secret-key', 'secret-key', 'secret-key'], array_column($operations, 'key'));
        $this->assertInstanceOf(TraceableRateLimiterFactory::class, $factory);
    }

    public function testCompiledContainerTracesConfiguredFactoriesAndInjectedBuilder()
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class)->setPublic(true);
        $container->register('storage', InMemoryStorage::class);
        $container->register('rate_limiter.data_collector', RateLimiterDataCollector::class)->addArgument(new Reference('request_stack'))->setPublic(true);
        $container->register('limiter_builder', RateLimiterBuilder::class)->setArguments([new Reference('storage'), null])->setPublic(true);
        $container->register('limiter', RateLimiterFactory::class)
            ->setArguments([null, new Reference('storage'), null])
            ->setAbstract(true);
        $container->setDefinition('limiter.api', (new ChildDefinition('limiter'))
            ->replaceArgument(0, ['id' => 'api', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'])
            ->addTag('rate_limiter', ['name' => 'api'])
            ->setPublic(true));
        $container->register('limiter.custom', TracingCustomFactory::class)
            ->addArgument(new Reference('limiter_builder'))
            ->addTag('rate_limiter', ['name' => 'custom'])
            ->setPublic(true);
        $container->register('limiter.plain', PlainCustomFactory::class)
            ->addTag('rate_limiter', ['name' => 'plain'])
            ->setPublic(true);
        $container->register('limiter.both', CompoundRateLimiterFactory::class)
            ->setArguments([new IteratorArgument(['api' => new Reference('limiter.api')])])
            ->addTag('rate_limiter', ['name' => 'both'])
            ->setPublic(true);

        (new RateLimiterTracePass())->process($container);
        $container->compile();

        $request = Request::create('/');
        $container->get('request_stack')->push($request);
        $this->assertInstanceOf(TraceableRateLimiterFactory::class, $container->get('limiter.api'));
        $this->assertInstanceOf(TraceableRateLimiterBuilder::class, $container->get('limiter_builder'));
        $container->get('limiter.api')->create('secret')->consume();
        $container->get('limiter_builder')->slidingWindow('dynamic', 3, '1 minute')->create('secret')->consume();
        $container->get('limiter.custom')->create('secret')->consume();
        $container->get('limiter.plain')->create('secret')->consume();
        $container->get('limiter.both')->create('secret')->consume();

        $operations = $this->operations($container->get('rate_limiter.data_collector'), $request);
        $this->assertSame(['api', 'dynamic', 'custom_inner', 'plain', 'both', 'api'], array_column($operations, 'limiter'));
        $this->assertSame(['fixed_window', 'sliding_window', 'fixed_window', null, 'compound', 'fixed_window'], array_column($operations, 'policy'));
        $this->assertSame([null, null, null, null, null, $operations[4]['id']], array_column($operations, 'parent'));
    }

    public function testNothingIsDecoratedWithoutTheDataCollector()
    {
        $container = new ContainerBuilder();
        $container->register('storage', InMemoryStorage::class);
        $container->register('limiter_builder', RateLimiterBuilder::class)->setArguments([new Reference('storage'), null])->setPublic(true);
        $container->register('limiter.plain', PlainCustomFactory::class)
            ->addTag('rate_limiter', ['name' => 'plain'])
            ->setPublic(true);

        (new RateLimiterTracePass())->process($container);
        $container->compile();

        $this->assertInstanceOf(RateLimiterBuilder::class, $container->get('limiter_builder'));
        $this->assertInstanceOf(PlainCustomFactory::class, $container->get('limiter.plain'));
    }

    public function testAbstractLimitersAreNotDecorated()
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class);
        $container->register('rate_limiter.data_collector', RateLimiterDataCollector::class)->addArgument(new Reference('request_stack'));
        $container->register('limiter.parent', PlainCustomFactory::class)
            ->setAbstract(true)
            ->addTag('rate_limiter', ['name' => 'parent']);
        $container->setDefinition('limiter.child', new ChildDefinition('limiter.parent'))
            ->addTag('rate_limiter', ['name' => 'child'])
            ->setPublic(true);

        (new RateLimiterTracePass())->process($container);
        $container->compile();

        $this->assertFalse($container->hasDefinition('debug.limiter.parent'));
        $this->assertInstanceOf(TraceableRateLimiterFactory::class, $container->get('limiter.child'));
    }

    public function testTheBuilderIsOnlyTracedWhenItExists()
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class);
        $container->register('rate_limiter.data_collector', RateLimiterDataCollector::class)->addArgument(new Reference('request_stack'));

        (new RateLimiterTracePass())->process($container);
        $container->compile();

        $this->assertFalse($container->has('limiter_builder'));
    }

    public function testPolicyIsKnownWhenTheConfigUsesEnvVars()
    {
        $container = new ContainerBuilder();
        $container->register('rate_limiter.data_collector', RateLimiterDataCollector::class);
        $container->register('limiter', RateLimiterFactory::class)->setAbstract(true);
        $container->setDefinition('limiter.static', (new ChildDefinition('limiter'))
            ->replaceArgument(0, ['id' => 'static', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'])
            ->addTag('rate_limiter', ['name' => 'static']));
        $container->setDefinition('limiter.env', (new ChildDefinition('limiter'))
            ->replaceArgument(0, ['id' => 'env', 'policy' => 'fixed_window', 'limit' => $container->getParameterBag()->get('env(int:LIMIT)'), 'interval' => '1 minute'])
            ->addTag('rate_limiter', ['name' => 'env']));

        (new RateLimiterTracePass())->process($container);

        $this->assertSame('fixed_window', $container->getDefinition('debug.limiter.static')->getArgument(3));
        $this->assertSame('fixed_window', $container->getDefinition('debug.limiter.env')->getArgument(3));
    }

    public function testCallsMadeAfterTheResponseAreCollectedLate()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $limiter = (new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector))->fixedWindow('api', 5, '1 minute')->create('k');
        $limiter->consume();

        $collector->collect($request, new Response());
        $this->assertCount(1, $collector->getOperations());

        $requests->pop();
        $limiter->consume();
        $collector->lateCollect();

        $this->assertCount(2, $collector->getOperations(), 'A call made after the request left the stack, e.g. on kernel.terminate, still belongs to it.');
        $this->assertSame(2, $collector->getTopLevelCount());
    }

    public function testCompoundRecordsNestedCallsWithoutCountingThemTwice()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $compound = $builder->compound(
            $builder->fixedWindow('short', 1, '1 minute'),
            $builder->fixedWindow('long', 2, '1 minute'),
        )->create('secret');

        $this->assertTrue($compound->consume()->isAccepted());
        $this->assertFalse($compound->consume()->isAccepted());

        $collector->collect($request, new Response());
        $this->assertSame(2, $collector->getTopLevelCount());
        $this->assertSame(1, $collector->getRejectedCount());
        $this->assertSame(0, $collector->getErrorCount());
        $operations = $collector->getOperations();
        $this->assertSame(['compound(short, long)', 'short', 'long', 'compound(short, long)', 'short'], array_column($operations, 'limiter'));
        $this->assertSame(['compound', 'fixed_window', 'fixed_window', 'compound', 'fixed_window'], array_column($operations, 'policy'));
        $this->assertSame(['secret', 'secret', 'secret', 'secret', 'secret'], array_column($operations, 'key'));
        $this->assertSame($operations[0]['id'], $operations[1]['parent']);
        $this->assertSame($operations[0]['id'], $operations[2]['parent']);
        $this->assertSame($operations[3]['id'], $operations[4]['parent']);

        $saved = unserialize(serialize($collector));
        $this->assertSame(2, $saved->getTopLevelCount());
        $this->assertSame($operations, $saved->getOperations());
    }

    public function testOperationsAreGroupedByLimiterAndKey()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $short = $builder->fixedWindow('short', 1, '1 minute');
        $compound = $builder->compound($short, $builder->slidingWindow('long', 5, '1 hour'))->create('a');
        $compound->consume();
        $compound->consume();
        $short->create('b')->consume();
        $builder->tokenBucket('bucket', 10, '1 second', 2)->create('a')->consume();

        $collector->collect($request, new Response());
        $limiters = $collector->getLimiters();

        $this->assertSame(['compound(short, long)', 'short', 'long', 'bucket'], array_keys($limiters));
        $this->assertSame(['short', 'long'], $limiters['compound(short, long)']['children']);

        [$accepted, $rejected] = $limiters['compound(short, long)']['keys']['a'];
        $this->assertSame(['short', 'long'], array_column($accepted['children'], 'limiter'));
        $this->assertSame(['1', '2'], [$accepted['number'], $rejected['number']]);
        $this->assertSame(['1.1', '1.2'], array_column($accepted['children'], 'number'), 'Nested calls are numbered after the call that made them.');
        $this->assertSame(['2.1', '3'], [$rejected['children'][0]['number'], $limiters['short']['keys']['b'][0]['number']]);
        $this->assertSame(['short'], array_column($rejected['children'], 'limiter'));
        $this->assertSame(['a', 'b'], array_keys($limiters['short']['keys']));
        $this->assertSame(array_column($limiters['compound(short, long)']['keys']['a'], 'id'), array_column($limiters['short']['keys']['a'], 'parent'));
        $this->assertSame([true, false], array_column($limiters['compound(short, long)']['keys']['a'], 'accepted'));

        $timeline = $collector->getTimeline();
        $this->assertSame(['compound(short, long)', 'compound(short, long)', 'short', 'bucket'], array_column($timeline, 'limiter'));
        $this->assertSame(['short', 'long'], array_column($timeline[0]['children'], 'limiter'));
        $this->assertSame(['short'], array_column($timeline[1]['children'], 'limiter'), 'The rejected compound call stops before "long".');
    }

    public function testReservationsAndDisabledProfiling()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $reservation = $builder->tokenBucket('bucket', 2, '1 minute')->create('secret')->reserve();

        $this->assertTrue($reservation->getRateLimit()->isAccepted());
        $this->assertSame('reserve', $this->operations($collector, $request)[0]['operation']);
        $this->assertNotNull($this->operations($collector, $request)[0]['timeToAct']);

        $limiter = $builder->tokenBucket('future', 1, '1 minute')->create('secret');
        $limiter->reserve();
        $this->assertFalse($limiter->reserve()->getRateLimit()->isAccepted());

        $collector->collect($request, new Response());
        $this->assertSame(0, $collector->getRejectedCount(), 'A reservation for later is not a rejection.');

        $disabled = new RateLimiterDataCollector($requests, static fn () => true);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $disabled);
        $this->assertTrue($builder->fixedWindow('disabled', 1, '1 minute')->create()->consume()->isAccepted());
        $this->assertSame([], $this->operations($disabled, $request));
    }

    public function testEveryBuilderMethodCanProduceTracedFactories()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);

        foreach (['fixed', 'sliding', 'bucket', 'noop'] as $policy) {
            $factory = match ($policy) {
                'fixed' => $builder->fixedWindow($policy, 2, '1 minute'),
                'sliding' => $builder->slidingWindow($policy, 2, '1 minute'),
                'bucket' => $builder->tokenBucket($policy, 2, '1 minute'),
                'noop' => $builder->noop(),
            };
            $this->assertInstanceOf(TraceableRateLimiterFactory::class, $factory);
            $factory->create('secret')->consume();
        }

        $this->assertSame(['fixed', 'sliding', 'bucket', 'noop'], array_column($this->operations($collector, $request), 'limiter'));
        $this->assertSame(['fixed_window', 'sliding_window', 'token_bucket', 'no_limit'], array_column($this->operations($collector, $request), 'policy'));
        $this->assertTrue($this->operations($collector, $request)[3]['unlimited']);
    }

    public function testOperationsWithoutKeyAreGroupedTogether()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $factory = (new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector))->fixedWindow('api', 5, '1 minute');

        $factory->create()->consume();
        $factory->create('user-42')->consume();
        $factory->create()->consume();

        $collector->collect($request, new Response());
        $this->assertSame([null, 'user-42', null], array_column($collector->getOperations(), 'key'));
        $this->assertSame(['', 'user-42'], array_keys($collector->getLimiters()['api']['keys']));
    }

    public function testSubrequestOperationsKeepTheirOwnParents()
    {
        $requests = new RequestStack();
        $parent = Request::create('/parent');
        $child = Request::create('/child');
        $requests->push($parent);
        $collector = new RateLimiterDataCollector($requests);
        $collector->record('parent', 'consume', 1, static function () use ($requests, $child, $collector) {
            $requests->push($child);
            try {
                $collector->record('child', 'consume', 1, static fn () => null);
            } finally {
                $requests->pop();
            }
        });

        $this->assertNull($this->operations($collector, $parent)[0]['parent']);
        $this->assertNull($this->operations($collector, $child)[0]['parent']);

        $collector->collect($child, new Response());
        $this->assertSame(['child'], array_column($collector->getOperations(), 'limiter'), 'Sub-request operations belong to the sub-request profile.');

        $collector->collect($parent, new Response());
        $this->assertSame(['parent'], array_column($collector->getOperations(), 'limiter'));
    }

    public function testFailureIsRecordedAndTheExceptionRethrown()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $exception = new \RuntimeException('private details');

        try {
            $collector->record('api', 'consume', 1, static function () use ($exception) { throw $exception; });
            $this->fail('The original exception should be rethrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }
        $collector->collect($request, new Response());
        $this->assertSame(1, $collector->getErrorCount());
        $this->assertTrue($collector->getOperations()[0]['failed']);
        $this->assertSame(['class' => \RuntimeException::class, 'message' => 'private details'], $collector->getOperations()[0]['exception']);
    }

    public function testReservationExceedingItsMaximumWaitIsARejection()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $limiter = (new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector))->tokenBucket('bucket', 1, '1 hour')->create('a');
        $limiter->reserve();

        try {
            $limiter->reserve(1, 1);
            $this->fail('The reservation should exceed its maximum wait.');
        } catch (MaxWaitDurationExceededException) {
        }
        $collector->collect($request, new Response());
        $this->assertSame([1, 0], [$collector->getRejectedCount(), $collector->getErrorCount()]);
        $operation = $collector->getOperations()[1];
        $this->assertFalse($operation['failed']);
        $this->assertSame([false, 0, 1], [$operation['accepted'], $operation['remaining'], $operation['limit']]);
        $this->assertNotNull($operation['retryAfter']);
        $this->assertSame(MaxWaitDurationExceededException::class, $operation['exception']['class']);
    }

    public function testBuilderCompoundsAreNamedAfterTheirLimiters()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $builder->compound($builder->fixedWindow('a', 5, '1 minute'))->create('k')->consume();
        $builder->compound($builder->compound($builder->fixedWindow('b', 5, '1 minute')))->create('k')->consume();

        $collector->collect($request, new Response());
        $limiters = $collector->getLimiters();
        $this->assertSame(['compound(a)', 'a', 'compound(compound(b))', 'compound(b)', 'b'], array_keys($limiters));
        $this->assertSame(['a'], array_column($limiters['compound(a)']['keys']['k'][0]['children'], 'limiter'));

        $inner = $limiters['compound(compound(b))']['keys']['k'][0]['children'][0];
        $this->assertSame('compound(b)', $inner['limiter']);
        $this->assertSame(['b'], array_column($inner['children'], 'limiter'));
    }

    public function testLimitersOfACompoundComeRightAfterIt()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $short = $builder->fixedWindow('short', 5, '1 minute');
        $short->create('k')->consume();
        $builder->fixedWindow('other', 5, '1 minute')->create('k')->consume();
        $builder->compound($short, $builder->fixedWindow('long', 5, '1 hour'))->create('k')->consume();

        $collector->collect($request, new Response());

        $this->assertSame(['other', 'compound(short, long)', 'short', 'long'], array_keys($collector->getLimiters()), 'A limiter called before its compound still follows it.');
    }

    public function testTopLevelOperationsRecordWhereTheyWereCalledFrom()
    {
        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $compound = $builder->compound($builder->fixedWindow('short', 1, '1 minute'))->create('a');

        $line = __LINE__ + 1;
        $compound->consume();

        $operations = $this->operations($collector, $request);
        $this->assertSame(['attribute' => false, 'controller' => null, 'file' => __FILE__, 'line' => $line], $operations[0]['origin']);
        $this->assertNull($operations[1]['origin'], 'Calls made by a compound limiter come from the compound call.');
    }

    public function testRateLimitAttributeCallsRecordTheController()
    {
        $requests = new RequestStack();
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $listener = new RateLimitAttributeListener(new ServiceLocator(['api' => static fn () => $builder->fixedWindow('api', 5, '1 minute')]));
        $consume = function (?string $controller) use ($requests, $collector, $listener): ?array {
            $request = Request::create('/');
            $request->attributes->set('_controller', $controller);
            $requests->push($request);
            $listener->onKernelControllerAttribute(new ControllerAttributeEvent(new RateLimit('api'), new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn () => null, $request, HttpKernelInterface::MAIN_REQUEST)));
            $requests->pop();

            return $this->operations($collector, $request)[0]['origin'];
        };

        $this->assertSame(['attribute' => true, 'controller' => 'App\Controller\ShopController::buy', 'file' => null, 'line' => null], $consume('App\Controller\ShopController::buy'));
        $this->assertSame(['attribute' => true, 'controller' => null, 'file' => null, 'line' => null], $consume(null), 'An unknown controller still marks the call as coming from the attribute.');
    }

    private function operations(RateLimiterDataCollector $collector, Request $request): array
    {
        $collector->collect($request, new Response());

        return $collector->getOperations();
    }
}

class TracingCustomFactory implements RateLimiterFactoryInterface
{
    public function __construct(private RateLimiterBuilderInterface $builder)
    {
    }

    public function create(?string $key = null): LimiterInterface
    {
        return $this->builder->fixedWindow('custom_inner', 2, '1 minute')->create($key);
    }
}

class PlainCustomFactory implements RateLimiterFactoryInterface
{
    public function create(?string $key = null): LimiterInterface
    {
        return new NoLimiter();
    }
}
