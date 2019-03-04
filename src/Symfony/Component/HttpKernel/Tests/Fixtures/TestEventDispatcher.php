<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;

class TestEventDispatcher extends TraceableEventDispatcher
{
    public function getCalledListeners()
    {
        return ['foo'];
    }

    public function getNotCalledListeners()
    {
        return ['bar'];
    }

    public function reset()
    {
    }

    public function getOrphanedEvents()
    {
        return [];
    }
}
