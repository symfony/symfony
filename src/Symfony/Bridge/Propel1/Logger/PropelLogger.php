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
 */
class PropelLogger implements \BasicLogger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $queries = array();

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var Boolean
     */
    private $isPrepared = false;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger    A LoggerInterface instance
     * @param Stopwatch       $stopwatch A Stopwatch instance
     *
     * @since v2.1.0
     */
    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger    = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function alert($message)
    {
        $this->log($message, 'alert');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function crit($message)
    {
        $this->log($message, 'crit');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function err($message)
    {
        $this->log($message, 'err');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function warning($message)
    {
        $this->log($message, 'warning');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function notice($message)
    {
        $this->log($message, 'notice');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function info($message)
    {
        $this->log($message, 'info');
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function debug($message)
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
            $this->log($message, 'debug');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function log($message, $severity = null)
    {
        if (null !== $this->logger) {
            $message = is_string($message) ? $message : var_export($message, true);

            switch ($severity) {
                case 'alert':
                    $this->logger->alert($message);
                    break;
                case 'crit':
                    $this->logger->critical($message);
                    break;
                case 'err':
                    $this->logger->error($message);
                    break;
                case 'warning':
                    $this->logger->warning($message);
                    break;
                case 'notice':
                    $this->logger->notice($message);
                    break;
                case 'info':
                    $this->logger->info($message);
                    break;
                case 'debug':
                default:
                    $this->logger->debug($message);
            }
        }
    }

    /**
     * Returns queries.
     *
     * @return array Queries
     *
     * @since v2.1.0
     */
    public function getQueries()
    {
        return $this->queries;
    }
}
