<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TimerDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TimerDataCollector extends DataCollector
{
    protected $kernel;

    public function __construct(KernelInterface $kernel)
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
