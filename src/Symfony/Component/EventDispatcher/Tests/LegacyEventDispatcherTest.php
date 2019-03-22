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

/**
 * @group legacy
 */
class LegacyEventDispatcherTest extends EventDispatcherTest
{
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
