<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Doctrine\Common\EventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ContainerAwareEventManagerTest extends TestCase
{
    private Container $container;
    private ContainerAwareEventManager $evm;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->evm = new ContainerAwareEventManager($this->container);
    }

    public function testDispatchEventRespectOrder()
    {
        $this->evm = new ContainerAwareEventManager($this->container, [[['foo'], 'list1'], [['foo'], 'list2']]);

        $this->container->set('list1', $listener1 = new MyListener());
        $this->container->set('list2', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners('foo')));
    }

    public function testUsingDoctrineSubscribersThrows()
    {
        $this->evm = new ContainerAwareEventManager($this->container, [new MySubscriber(['foo'])]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Using Doctrine subscriber "Symfony\Bridge\Doctrine\Tests\MySubscriber" is not allowed. Register it as a listener instead, using e.g. the #[AsDoctrineListener] or #[AsDocumentListener] attribute.');
        $this->evm->getListeners('foo');
    }

    public function testDispatchEvent()
    {
        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->container->set('lazy2', $listener3 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy2');
        $this->evm->addEventListener('bar', $listener4 = new MyListener());
        $this->container->set('lazy3', $listener5 = new MyListener());
        $this->evm->addEventListener('foo', $listener5 = new MyListener());
        $this->evm->addEventListener('bar', $listener5);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(1, $listener1->calledByEventNameCount);
        $this->assertSame(0, $listener2->calledByInvokeCount);
        $this->assertSame(1, $listener2->calledByEventNameCount);
        $this->assertSame(1, $listener3->calledByInvokeCount);
        $this->assertSame(0, $listener3->calledByEventNameCount);
        $this->assertSame(1, $listener4->calledByInvokeCount);
        $this->assertSame(0, $listener4->calledByEventNameCount);
        $this->assertSame(1, $listener5->calledByInvokeCount);
        $this->assertSame(1, $listener5->calledByEventNameCount);
    }

    public function testAddEventListenerAfterDispatchEvent()
    {
        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->container->set('lazy2', $listener3 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy2');
        $this->evm->addEventListener('bar', $listener4 = new MyListener());
        $this->container->set('lazy3', $listener5 = new MyListener());
        $this->evm->addEventListener('foo', $listener5 = new MyListener());
        $this->evm->addEventListener('bar', $listener5);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->container->set('lazy4', $listener6 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy4');
        $this->evm->addEventListener('foo', $listener7 = new MyListener());
        $this->container->set('lazy5', $listener8 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy5');
        $this->evm->addEventListener('bar', $listener9 = new MyListener());
        $this->container->set('lazy6', $listener10 = new MyListener());
        $this->evm->addEventListener('foo', $listener10 = new MyListener());
        $this->evm->addEventListener('bar', $listener10);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(2, $listener1->calledByEventNameCount);
        $this->assertSame(0, $listener2->calledByInvokeCount);
        $this->assertSame(2, $listener2->calledByEventNameCount);
        $this->assertSame(2, $listener3->calledByInvokeCount);
        $this->assertSame(0, $listener3->calledByEventNameCount);
        $this->assertSame(2, $listener4->calledByInvokeCount);
        $this->assertSame(0, $listener4->calledByEventNameCount);
        $this->assertSame(2, $listener5->calledByInvokeCount);
        $this->assertSame(2, $listener5->calledByEventNameCount);

        $this->assertSame(0, $listener6->calledByInvokeCount);
        $this->assertSame(1, $listener6->calledByEventNameCount);
        $this->assertSame(0, $listener7->calledByInvokeCount);
        $this->assertSame(1, $listener7->calledByEventNameCount);
        $this->assertSame(1, $listener8->calledByInvokeCount);
        $this->assertSame(0, $listener8->calledByEventNameCount);
        $this->assertSame(1, $listener9->calledByInvokeCount);
        $this->assertSame(0, $listener9->calledByEventNameCount);
        $this->assertSame(1, $listener10->calledByInvokeCount);
        $this->assertSame(1, $listener10->calledByEventNameCount);
    }

    public function testGetListenersForEvent()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners('foo')));
    }

    public function testGetAllListeners()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getAllListeners()['foo']));
    }

    public function testGetListenersWhenALazyListenerAddsListeners()
    {
        $listener1 = new MyListener();
        $listener2 = new MyListener();
        $listener3 = new MyListener();
        $evm = null;
        $container = new ServiceLocator(['lazy2' => static function () use (&$evm, $listener2, $listener3) {
            $evm->addEventListener('foo', $listener3);

            return $listener2;
        }]);
        $evm = new ContainerAwareEventManager($container);

        $evm->addEventListener('foo', $listener1);
        $evm->addEventListener('foo', 'lazy2');

        $expected = [$listener1, $listener2, $listener3];

        $this->assertSame($expected, array_values($evm->getListeners('foo')));
        $this->assertSame($expected, array_values($evm->getListeners('foo')));
    }

    public function testRemoveEventListener()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->evm->removeEventListener('foo', $listener2);
        $this->assertSame([$listener1], array_values($this->evm->getListeners('foo')));

        $this->evm->removeEventListener('foo', 'lazy');
        $this->assertSame([], $this->evm->getListeners('foo'));
    }

    public function testRemoveLazyEventListenerByObject()
    {
        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->container->set('lazy2', $listener2 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', 'lazy2');

        $this->evm->removeEventListener('foo', $listener1);
        $this->assertSame([$listener2], array_values($this->evm->getListeners('foo')));

        $this->evm->removeEventListener('foo', $listener2);
        $this->assertSame([], $this->evm->getListeners('foo'));
    }

    public function testRemoveLazyEventListenerByObjectKeepsListenersLazy()
    {
        $called = 0;
        $listener = new MyListener();
        $evm = new ContainerAwareEventManager(new ServiceLocator([
            'lazy1' => static function () use (&$called, $listener) {
                ++$called;

                return $listener;
            },
            'lazy2' => static function () use (&$called) {
                ++$called;

                return new MyListener();
            },
        ]));
        $evm->addEventListener('foo', 'lazy1');
        $evm->addEventListener('foo', 'lazy2');

        $evm->removeEventListener('foo', $listener);
        $this->assertSame(0, $called);

        $this->assertCount(1, $evm->getListeners('foo'));
    }

    public function testDispatchEventAfterRemovingLazyEventListenerByObject()
    {
        $this->container->set('lazy', $listener = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');

        $this->evm->removeEventListener('foo', $listener);
        $this->evm->dispatchEvent('foo');

        $this->assertSame(0, $listener->calledByEventNameCount);
    }

    public function testRemoveLazyEventSubscriberByObject()
    {
        $this->container->set('lazy', $subscriber = new MySubscriber(['foo']));
        $this->evm->addEventListener('foo', 'lazy');

        $this->evm->removeEventSubscriber($subscriber);

        $this->assertSame([], $this->evm->getListeners('foo'));
    }

    public function testRemoveEventListenerOnSeveralEvents()
    {
        $this->container->set('lazy', new MyListener());
        $this->evm->addEventListener(['foo', 'bar'], 'lazy');

        $this->evm->getListeners('foo');
        $this->evm->removeEventListener(['foo', 'bar'], 'lazy');

        $this->assertSame([], $this->evm->getListeners('foo'));
        $this->assertSame([], $this->evm->getListeners('bar'));
    }

    public function testRemoveAllEventListener()
    {
        $this->container->set('lazy', new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', new MyListener());

        foreach ($this->evm->getAllListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->evm->removeEventListener($event, $listener);
            }
        }

        $this->assertSame([], $this->evm->getListeners('foo'));
    }

    public function testRemoveEventListenerAfterDispatchEvent()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->evm->dispatchEvent('foo');

        $this->evm->removeEventListener('foo', $listener2);
        $this->assertSame([$listener1], array_values($this->evm->getListeners('foo')));

        $this->evm->removeEventListener('foo', 'lazy');
        $this->assertSame([], $this->evm->getListeners('foo'));
    }
}

class MyListener
{
    public int $calledByInvokeCount = 0;
    public int $calledByEventNameCount = 0;

    public function __invoke(): void
    {
        ++$this->calledByInvokeCount;
    }

    public function foo(): void
    {
        ++$this->calledByEventNameCount;
    }
}

class MySubscriber extends MyListener implements EventSubscriber
{
    public int $calledSubscribedEventsCount = 0;
    private array $listenedEvents;

    public function __construct(array $listenedEvents)
    {
        $this->listenedEvents = $listenedEvents;
    }

    public function getSubscribedEvents(): array
    {
        ++$this->calledSubscribedEventsCount;

        return $this->listenedEvents;
    }
}
