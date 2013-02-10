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

/**
 * MemoryDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MemoryDataCollector extends DataCollector
{
    public function __construct()
    {
        $this->data = array(
            'memory' => 0,
            'memory_limit' => rtrim(ini_get('memory_limit'), 'M')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->updateMemoryUsage();
    }

    /**
     * Gets the memory.
     *
     * @return integer The memory
     */
    public function getMemory()
    {
        return $this->data['memory'];
    }

    /**
     * Gets the PHP memory limit.
     *
     * @return integer The memory limit
     */
    public function getMemoryLimit()
    {
        return $this->data['memory_limit'];
    }

    /**
     * Updates the memory usage data.
     */
    public function updateMemoryUsage()
    {
        $this->data['memory'] = memory_get_peak_usage(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'memory';
    }
}
