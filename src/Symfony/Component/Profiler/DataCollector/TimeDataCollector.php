<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Profiler\ProfileData\TimeData;

/**
 * TimeDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TimeDataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    protected $requestStack;
    protected $kernel;
    protected $stopwatch;
    protected $startTime;

    public function __construct(RequestStack $requestStack, KernelInterface $kernel = null, Stopwatch $stopwatch = null)
    {
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
        $this->stopwatch = $stopwatch;
        $this->startTime = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $this->kernel) {
            $this->startTime = $this->kernel->getStartTime();
        } else if (  null !== $request  ) {
            $this->startTime = $request->server->get('REQUEST_TIME_FLOAT', $request->server->get('REQUEST_TIME'));
        }

        $events = array();
        if (null !== $this->stopwatch && $this->token) {
            $events = $this->stopwatch->getSectionEvents($this->token);
        }
        unset($this->token);

        return new TimeData($this->startTime, $events);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'time';
    }
}
