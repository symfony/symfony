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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Profiler\ProfileData\MemoryData;

/**
 * MemoryDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MemoryDataCollector extends AbstractDataCollector implements LateDataCollectorInterface, RuntimeDataCollectorInterface
{
    protected $memoryLimit;

    public function __construct()
    {
        $this->memoryLimit = ini_get('memory_limit');
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        return new MemoryData(memory_get_peak_usage(true), $this->memoryLimit);
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        return new MemoryData(memory_get_peak_usage(true), $this->memoryLimit);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'memory';
    }
}
