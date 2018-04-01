<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures;

use Symphony\Component\EventDispatcher\Debug\TraceableEventDispatcher;

class TestEventDispatcher extends TraceableEventDispatcher
{
    public function getCalledListeners()
    {
        return array('foo');
    }

    public function getNotCalledListeners()
    {
        return array('bar');
    }

    public function reset()
    {
    }

    public function getOrphanedEvents()
    {
        return array();
    }
}
