<?php

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\Kernel;
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
 * TimerDataCollector.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TimerDataCollector extends DataCollector
{
    protected $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'time' => microtime(true) - $this->kernel->getStartTime(),
        );
    }

    /**
     * Gets the request time.
     *
     * @return integer The time
     */
    public function getTime()
    {
        return $this->data['time'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'timer';
    }
}
