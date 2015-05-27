<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcherInterface;
use Symfony\Component\Profiler\DataCollector\EventDataCollector as BaseEventDataCollector;

/**
 * EventDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @deprecated since x.x, to be removed in x.x. Use Symfony\Component\Profiler\DataCollector\EventDataCollector instead.
 */
class EventDataCollector extends BaseEventDataCollector
{

}
