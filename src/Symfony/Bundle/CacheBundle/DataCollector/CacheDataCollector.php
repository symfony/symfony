<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Cache\CacheProfiler;

/**
 * CacheDataCollector.
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class CacheDataCollector extends DataCollector
{

    private $cacheProfiler;

    public function __construct(CacheProfiler $cacheProfiler)
    {
        $this->cacheProfiler = $cacheProfiler;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = $this->cacheProfiler->getResults();
    }

    public function getTotalHits()
    {
        return $this->data['hits'];
    }

    public function getTotalMisses()
    {
        return $this->data['ops'] - $this->data['hits'];
    }

    public function getTotalOps()
    {
        return $this->data['ops'];
    }

    public function getTotalTime()
    {
        return $this->data['time'];
    }

    public function getDrivers()
    {
        return $this->data['drivers'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'cache';
    }
}
