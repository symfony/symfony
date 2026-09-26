<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class EventDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $c = $this->collect();

        $called = $c->getCalledListeners('event_dispatcher');
        $this->assertInstanceOf(Data::class, $called);
        $this->assertCount(1, $called);
        $this->assertSame('foo', $called[0]['event']);
        $this->assertSame(EventDataCollectorTestListener::class.'::onFoo', $called[0]['pretty']);
        $this->assertSame($this->dump(new ClassStub(EventDataCollectorTestListener::class.'::onFoo()', EventDataCollectorTestListener::class.'::onFoo')), $this->dump($called[0]['stub']));

        $notCalled = $c->getNotCalledListeners('event_dispatcher');
        $this->assertCount(2, $notCalled);
        $this->assertSame('bar', $notCalled[0]['event']);
        $this->assertSame($this->dump(new ClassStub(EventDataCollectorTestListener::class.'::onBar()', EventDataCollectorTestListener::class.'::onBar')), $this->dump($notCalled[0]['stub']));
        $this->assertSame('baz', $notCalled[1]['event']);
        $this->assertSame('"closure()"', $this->dump($notCalled[1]['stub']));

        $this->assertSame(['qux'], $c->getOrphanedEvents('event_dispatcher')->getValue(true));
    }

    public function testListenerStubsAreNotCollected()
    {
        $this->assertStringNotContainsString(EventDataCollectorTestListener::class.'::onBar(', serialize($this->collect()));
    }

    public function testDataOfPreviousVersions()
    {
        $stub = new ClassStub(EventDataCollectorTestListener::class.'::onFoo()', EventDataCollectorTestListener::class.'::onFoo');
        $data = (new VarCloner())->cloneVar(['event_dispatcher' => [
            'called_listeners' => [['event' => 'foo', 'priority' => 0, 'pretty' => EventDataCollectorTestListener::class.'::onFoo', 'stub' => $stub]],
            'not_called_listeners' => [],
            'orphaned_events' => [],
        ]]);
        $c = new EventDataCollector();
        $c->__unserialize(['data' => $data]);

        $this->assertSame($data, $c->getData());
        $this->assertSame($this->dump($stub), $this->dump($c->getCalledListeners('event_dispatcher')[0]['stub']));
    }

    public function testGettersOfTheDefaultDispatcherWorkAfterUnserialize()
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('called', static function () {});
        $dispatcher->addListener('not_called', static function () {});
        $dispatcher->dispatch(new \stdClass(), 'called');
        $dispatcher->dispatch(new \stdClass(), 'orphaned');

        $collector = new EventDataCollector($dispatcher);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $collector = unserialize(serialize($collector));

        $this->assertCount(1, $collector->getCalledListeners());
        $this->assertCount(1, $collector->getNotCalledListeners());
        $this->assertCount(1, $collector->getOrphanedEvents());
    }

    private function collect(): EventDataCollector
    {
        $dispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $dispatcher->addListener('foo', [new EventDataCollectorTestListener(), 'onFoo']);
        $dispatcher->addListener('bar', [new EventDataCollectorTestListener(), 'onBar']);
        $dispatcher->addListener('baz', static function () {});
        $dispatcher->dispatch(new \stdClass(), 'foo');
        $dispatcher->dispatch(new \stdClass(), 'qux');

        $c = new EventDataCollector($dispatcher);
        $c->collect(new Request(), new Response());
        $c->lateCollect();

        return unserialize(serialize($c));
    }

    private function dump(Data|ClassStub $var): string
    {
        if ($var instanceof ClassStub) {
            $var = (new VarCloner())->cloneVar([$var])->seek(0);
        }
        $dumper = new CliDumper();
        $dumper->setColors(false);

        return rtrim($dumper->dump($var, true));
    }
}

class EventDataCollectorTestListener
{
    public function onFoo(object $event): void
    {
    }

    public function onBar(object $event): void
    {
    }
}
