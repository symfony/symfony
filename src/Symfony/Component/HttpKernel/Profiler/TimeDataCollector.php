<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Profiler\DataCollector\TimeDataCollector as BaseTimeDataCollector;

/**
 * TimeDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TimeDataCollector extends BaseTimeDataCollector
{
    private $requestStack;
    private $kernel;

    /**
     * Constructor.
     *
     * @param RequestStack $requestStack
     * @param KernelInterface|null $kernel
     * @param Stopwatch|null $stopwatch
     */
    public function __construct(RequestStack $requestStack, KernelInterface $kernel = null, Stopwatch $stopwatch = null)
    {
        parent::__construct($stopwatch);
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $this->kernel) {
            $this->startTime = $this->kernel->getStartTime();
        } elseif (null !== $request) {
            $this->startTime = $request->server->get('REQUEST_TIME_FLOAT', $request->server->get('REQUEST_TIME'));
        }

        return parent::getCollectedData();
    }
}
