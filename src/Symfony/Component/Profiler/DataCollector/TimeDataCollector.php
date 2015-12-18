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

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Profiler\ProfileData\TimeData;

/**
 * TimeDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TimeDataCollector implements LateDataCollectorInterface
{
    /**
     * @var null|Stopwatch
     */
    private $stopwatch;

    /**
     * @var int
     */
    protected $startTime;

    /**
     * Constructor.
     *
     * @param Stopwatch|null $stopwatch
     */
    public function __construct(Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch;
        $this->startTime = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        return new TimeData($this->startTime, $this->stopwatch);
    }
}
