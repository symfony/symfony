<?php

namespace Symfony\Bundle\PropelBundle\Logger;

use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PropelLogger.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PropelLogger implements \BasicLogger
{
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Log message.
     *
     * @param string $message  The message to log
     * @param int    $severity The numeric severity
     */
    public function log($message, $severity = 6)
    {
        if (null !== $this->logger) {
            $this->logger->log($message, $severity);
        }
    }

    /**
     * A convenience function for logging an alert event.
     *
     * @param mixed $message the message to log.
     */
    public function alert($message)
    {
      $this->log($message, 1);
    }

    /**
     * A convenience function for logging a critical event.
     *
     * @param mixed $message the message to log.
     */
    public function crit($message)
    {
      $this->log($message, 2);
    }

    /**
     * A convenience function for logging an error event.
     *
     * @param mixed $message the message to log.
     */
    public function err($message)
    {
      $this->log($message, 3);
    }

    /**
     * A convenience function for logging a warning event.
     *
     * @param mixed $message the message to log.
     */
    public function warning($message)
    {
      $this->log($message, 4);
    }

    /**
     * A convenience function for logging an critical event.
     *
     * @param mixed $message the message to log.
     */
    public function notice($message)
    {
      $this->log($message, 5);
    }

    /**
     * A convenience function for logging an critical event.
     *
     * @param mixed $message the message to log.
     */
    public function info($message)
    {
      $this->log($message, 6);
    }

    /**
     * A convenience function for logging a debug event.
     *
     * @param mixed $message the message to log.
     */
    public function debug($message)
    {
      $this->log($message, 7);
    }
}
