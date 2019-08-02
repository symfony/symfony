<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Contracts\EventDispatcher\Event as ContractsEvent;

/**
 * @group legacy
 */
class LegacyEventDispatcherTest extends EventDispatcherTest
{
    /**
     * @group legacy
     * @expectedDeprecation The signature of the "Symfony\Component\EventDispatcher\Tests\TestLegacyEventDispatcher::dispatch()" method should be updated to "dispatch($event, string $eventName = null)", not doing so is deprecated since Symfony 4.3.
     * @expectedDeprecation Calling the "Symfony\Contracts\EventDispatcher\EventDispatcherInterface::dispatch()" method with the event name as the first argument is deprecated since Symfony 4.3, pass it as the second argument and provide the event object as the first argument instead.
     */
    public function testLegacySignatureWithoutEvent()
    {
        $this->createEventDispatcher()->dispatch('foo');
    }

    /**
     * @group legacy
     * @expectedDeprecation The signature of the "Symfony\Component\EventDispatcher\Tests\TestLegacyEventDispatcher::dispatch()" method should be updated to "dispatch($event, string $eventName = null)", not doing so is deprecated since Symfony 4.3.
     * @expectedDeprecation Calling the "Symfony\Contracts\EventDispatcher\EventDispatcherInterface::dispatch()" method with the event name as the first argument is deprecated since Symfony 4.3, pass it as the second argument and provide the event object as the first argument instead.
     */
    public function testLegacySignatureWithEvent()
    {
        $this->createEventDispatcher()->dispatch('foo', new Event());
    }

    public function testLegacySignatureWithNewEventObject()
    {
        $this->expectException('TypeError');
        $this->expectExceptionMessage('Argument 1 passed to "Symfony\Contracts\EventDispatcher\EventDispatcherInterface::dispatch()" must be an object, string given.');
        $this->createEventDispatcher()->dispatch('foo', new ContractsEvent());
    }

    protected function createEventDispatcher()
    {
        return LegacyEventDispatcherProxy::decorate(new TestLegacyEventDispatcher());
    }
}

class TestLegacyEventDispatcher extends EventDispatcher
{
    public function dispatch($eventName, Event $event = null)
    {
        return parent::dispatch($event, $eventName);
    }
}
