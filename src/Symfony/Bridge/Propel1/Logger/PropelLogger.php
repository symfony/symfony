<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Logger;

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;

/**
 * PropelLogger.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author William Durand <william.durand1@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class PropelLogger implements LoggerInterface
{
    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var array
     */
    protected $queries = array();

    /**
     * @var Stopwatch|null
     */
    protected $stopwatch;

    private $isPrepared = false;

    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger    An optional LoggerInterface instance
     * @param Stopwatch|null       $stopwatch An optional Stopwatch instance
     */
    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger    = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->emergency($message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->alert($message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->critical($message, $context);
        }
    }

    /**
     * A convenience function for logging a critical event.
     *
     * @param string $message the message to log.
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0. Use critical() instead.
     */
    public function crit($message)
    {
        if (null !== $this->logger) {
            $this->logger->critical($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * A convenience function for logging an error event.
     *
     * @param string $message the message to log.
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0. Use error() instead.
     */
    public function err($message)
    {
        if (null !== $this->logger) {
            $this->logger->error($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->notice($message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = array())
    {
        $add = true;

        if (null !== $this->stopwatch) {
            $trace = debug_backtrace();
            $method = $trace[2]['args'][2];

            $watch = 'Propel Query '.(count($this->queries)+1);
            if ('PropelPDO::prepare' === $method) {
                $this->isPrepared = true;
                $this->stopwatch->start($watch, 'propel');

                $add = false;
            } elseif ($this->isPrepared) {
                $this->isPrepared = false;
                $this->stopwatch->stop($watch);
            }
        }

        if ($add) {
            $this->queries[] = $message;
            if (null !== $this->logger) {
                $this->logger->debug($message, $context);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Returns queries.
     *
     * @return array Queries
     */
    public function getQueries()
    {
        return $this->queries;
    }
}
