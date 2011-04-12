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
 * LogDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LoggerDataCollector extends DataCollector
{
    private $logger;

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
     * @see TraceableEventDispatcherInterface
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
