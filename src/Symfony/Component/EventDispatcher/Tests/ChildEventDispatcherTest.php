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

use Symfony\Component\EventDispatcher\EventDispatcher;

class ChildEventDispatcherTest extends EventDispatcherTest
{
    protected function createEventDispatcher(array $listeners = [])
    {
        return new ChildEventDispatcher($listeners);
    }
}

class ChildEventDispatcher extends EventDispatcher
{
}
