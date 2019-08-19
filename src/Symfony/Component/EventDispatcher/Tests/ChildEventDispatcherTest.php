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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChildEventDispatcherTest extends EventDispatcherTest
{
    protected function createEventDispatcher(): EventDispatcherInterface
    {
        return new ChildEventDispatcher();
    }
}

class ChildEventDispatcher extends EventDispatcher
{
}
