<?php

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * MemoryDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class MemoryDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'memory' => memory_get_peak_usage(true),
        );
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
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'memory';
    }
}
