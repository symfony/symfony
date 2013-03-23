<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Cache profiler
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class CacheProfiler
{
    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * Operational results
     *
     * @var array
     */
    private $results = array();

    /**
     * Add a stopwatch
     *
     * @param Stopwatch $stopwatch
     */
    public function __construct(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    /**
     * Start the measurement of a new operation
     *
     * @param string $driverType
     * @param string $driverName
     * @param string $operation
     * @param string $key
     *
     * @return CacheProfiler
     */
    public function start($driverType, $driverName, $operation, $key = '')
    {
        $name = sprintf('%s_%s_%s_%s', $driverType, $driverName, $operation, $key);

        $this->stopwatch->start($name, 'cache');

        return $this;
    }

    /**
     * Stop the measurement as we've finished the operation
     *
     * @param string    $driverType
     * @param string    $driverName
     * @param string    $operation
     * @param string    $key
     * @param boolean   $result
     *
     * @return CacheProfiler
     */
    public function stop($driverType, $driverName, $operation, $key = '', $result)
    {
        $name = sprintf('%s_%s_%s_%s', $driverType, $driverName, $operation, $key);

        if ('' == $key) {
            $key = microtime(true);
        }

        $profile = $this->stopwatch->stop($name);

        $totalTime = $profile->getDuration();
        $result = (int)$result;

        $this->results['ops'] = isset($this->results['ops']) ? $this->results['ops'] + 1 : 1;
        $this->results['hits'] = isset($this->results['hits']) ? $this->results['hits'] + $result : $result;
        $this->results['time'] = isset($this->results['time']) ? $this->results['time'] + $totalTime : $totalTime;

        $this->results['drivers'][$driverType][$driverName][$operation][$key]['result'] = isset($this->results['drivers'][$driverType][$driverName][$operation][$key]['hits']) ? $this->results['hits'][$driverType][$driverName][$operation][$key]['hits'] + $result : $result;
        $this->results['drivers'][$driverType][$driverName][$operation][$key]['duration'] = isset($this->results['drivers'][$driverType][$driverName][$operation][$key]['time']) ? $this->results['time'][$driverType][$driverName][$operation][$key]['time'] + $result : $totalTime;

        return $this;
    }

    /**
     * Get the results of running the cache
     *
     * @static
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

}
