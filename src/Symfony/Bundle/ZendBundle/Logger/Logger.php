<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\Logger;

use Zend\Log\Logger as BaseLogger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * Logger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Logger extends BaseLogger implements LoggerInterface
{
    /**
     * Returns a DebugLoggerInterface instance if one is registered with this logger.
     *
     * @return DebugLoggerInterface A DebugLoggerInterface instance or null if none is registered
     */
    public function getDebugLogger()
    {
        foreach ($this->_writers as $writer) {
            if ($writer instanceof DebugLoggerInterface) {
                return $writer;
            }
        }

        return null;
    }

    public function emerg($message)
    {
        return parent::log($message, 0);
    }

    public function alert($message)
    {
        return parent::log($message, 1);
    }

    public function crit($message)
    {
        return parent::log($message, 2);
    }

    public function err($message)
    {
        return parent::log($message, 3);
    }

    public function warn($message)
    {
        return parent::log($message, 4);
    }

    public function notice($message)
    {
        return parent::log($message, 5);
    }

    public function info($message)
    {
        return parent::log($message, 6);
    }

    public function debug($message)
    {
        return parent::log($message, 7);
    }
}
