<?php

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\EventDispatcher\EventDispatcher;
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
 * LogDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LoggerDataCollector extends DataCollector
{
    protected $logger;

    public function __construct($logger = null)
    {
        if (null !== $logger) {
            $this->logger = $logger->getDebugLogger();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null !== $this->logger) {
            $this->data = array(
                'error_count' => $this->logger->countErrors(),
                'logs'        => $this->logger->getLogs(),
            );
        }
    }

    /**
     * Gets the called events.
     *
     * @return array An array of called events
     *
     * @see EventDispatcherTraceableInterface
     */
    public function countErrors()
    {
        return isset($this->data['error_count']) ? $this->data['error_count'] : 0;
    }

    /**
     * Gets the logs.
     *
     * @return array An array of logs
     */
    public function getLogs()
    {
        return isset($this->data['logs']) ? $this->data['logs'] : array();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logger';
    }
}
