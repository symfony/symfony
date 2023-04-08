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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\Container;

class ContainerAwareEventManagerTest extends TestCase
{
    use ExpectDeprecationTrait;

    private $container;
    private $evm;

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

    /**
     * @group legacy
     */
    public function testDispatchEventRespectOrderWithSubscribers()
    {
        $this->evm = new ContainerAwareEventManager($this->container, ['sub1', 'sub2']);

        $this->container->set('sub1', $subscriber1 = new MySubscriber(['foo']));
        $this->container->set('sub2', $subscriber2 = new MySubscriber(['foo']));

        $this->expectDeprecation('Since symfony/doctrine-bridge 6.3: Using Doctrine subscribers as services is deprecated, declare listeners instead');
        $this->assertSame([$subscriber1,  $subscriber2], array_values($this->evm->getListeners('foo')));
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

    /**
     * @group legacy
     */
    public function testDispatchEventWithSubscribers()
    {
        $this->evm = new ContainerAwareEventManager($this->container, ['lazy4']);

        $this->container->set('lazy4', $subscriber1 = new MySubscriber(['foo']));
        $this->assertSame(0, $subscriber1->calledSubscribedEventsCount);

        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->expectDeprecation('Since symfony/doctrine-bridge 6.3: Using Doctrine subscribers as services is deprecated, declare listeners instead');
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->evm->addEventSubscriber($subscriber2 = new MySubscriber(['bar']));

        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(1, $subscriber1->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(1, $listener1->calledByEventNameCount);
        $this->assertSame(0, $listener2->calledByInvokeCount);
        $this->assertSame(1, $listener2->calledByEventNameCount);
        $this->assertSame(0, $subscriber1->calledByInvokeCount);
        $this->assertSame(1, $subscriber1->calledByEventNameCount);
        $this->assertSame(1, $subscriber2->calledByInvokeCount);
        $this->assertSame(0, $subscriber2->calledByEventNameCount);
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

    /**
     * @group legacy
     */
    public function testAddEventListenerAndSubscriberAfterDispatchEvent()
    {
        $this->evm = new ContainerAwareEventManager($this->container, ['lazy7']);

        $this->container->set('lazy7', $subscriber1 = new MySubscriber(['foo']));
        $this->assertSame(0, $subscriber1->calledSubscribedEventsCount);

        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->expectDeprecation('Since symfony/doctrine-bridge 6.3: Using Doctrine subscribers as services is deprecated, declare listeners instead');
        $this->evm->addEventListener('foo', 'lazy1');
        $this->assertSame(1, $subscriber1->calledSubscribedEventsCount);

        $this->evm->addEventSubscriber($subscriber2 = new MySubscriber(['bar']));

        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(1, $subscriber1->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);

        $this->container->set('lazy6', $listener2 = new MyListener());
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->evm->addEventListener('bar', $listener2);
        $this->evm->addEventSubscriber($subscriber3 = new MySubscriber(['bar']));

        $this->assertSame(1, $subscriber1->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber3->calledSubscribedEventsCount);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(1, $subscriber1->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber2->calledSubscribedEventsCount);
        $this->assertSame(1, $subscriber3->calledSubscribedEventsCount);

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(2, $listener1->calledByEventNameCount);
        $this->assertSame(0, $subscriber1->calledByInvokeCount);
        $this->assertSame(2, $subscriber1->calledByEventNameCount);
        $this->assertSame(2, $subscriber2->calledByInvokeCount);
        $this->assertSame(0, $subscriber2->calledByEventNameCount);

        $this->assertSame(1, $listener2->calledByInvokeCount);
        $this->assertSame(1, $listener2->calledByEventNameCount);
        $this->assertSame(1, $subscriber3->calledByInvokeCount);
        $this->assertSame(0, $subscriber3->calledByEventNameCount);
    }

    public function testGetListenersForEvent()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners('foo')));
    }

    /**
     * @group legacy
     */
    public function testGetListenersForEventWhenSubscribersArePresent()
    {
        $this->evm = new ContainerAwareEventManager($this->container, ['lazy2']);

        $this->container->set('lazy', $listener1 = new MyListener());
        $this->container->set('lazy2', $subscriber1 = new MySubscriber(['foo']));
        $this->expectDeprecation('Since symfony/doctrine-bridge 6.3: Using Doctrine subscribers as services is deprecated, declare listeners instead');
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$subscriber1, $listener1, $listener2], array_values($this->evm->getListeners('foo')));
    }

    /**
     * @group legacy
     */
    public function testGetListeners()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->expectDeprecation('Since symfony/doctrine-bridge 6.2: Calling "Symfony\Bridge\Doctrine\ContainerAwareEventManager::getListeners()" without an event name is deprecated. Call "getAllListeners()" instead.');

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners()['foo']));
    }

    public function testGetAllListeners()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getAllListeners()['foo']));
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
    public $calledByInvokeCount = 0;
    public $calledByEventNameCount = 0;

    public function __invoke()
    {
        ++$this->calledByInvokeCount;
    }

    public function foo()
    {
        ++$this->calledByEventNameCount;
    }
}

class MySubscriber extends MyListener implements EventSubscriber
{
    public $calledSubscribedEventsCount = 0;
    private $listenedEvents;

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
