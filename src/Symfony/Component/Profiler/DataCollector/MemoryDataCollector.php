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

use Symfony\Component\Profiler\ProfileData\MemoryData;

/**
 * MemoryDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class MemoryDataCollector implements LateDataCollectorInterface
{
    private $memoryLimit;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->memoryLimit = ini_get('memory_limit');
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        return new MemoryData(memory_get_peak_usage(true), $this->memoryLimit);
    }
}
