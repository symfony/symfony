<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog;

use Monolog\Logger as BaseLogger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * Logger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Logger extends BaseLogger implements LoggerInterface, DebugLoggerInterface
{
    /**
     * @deprecated since version 2.2, to be removed in 3.0. Use emergency() which is PSR-3 compatible.
     */
    public function emerg($message, array $context = array())
    {
        @trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the emergency() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);

        return parent::addRecord(BaseLogger::EMERGENCY, $message, $context);
    }

    /**
     * @deprecated since version 2.2, to be removed in 3.0. Use critical() which is PSR-3 compatible.
     */
    public function crit($message, array $context = array())
    {
        @trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the method critical() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);

        return parent::addRecord(BaseLogger::CRITICAL, $message, $context);
    }

    /**
     * @deprecated since version 2.2, to be removed in 3.0. Use error() which is PSR-3 compatible.
     */
    public function err($message, array $context = array())
    {
        @trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the error() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);

        return parent::addRecord(BaseLogger::ERROR, $message, $context);
    }

    /**
     * @deprecated since version 2.2, to be removed in 3.0. Use warning() which is PSR-3 compatible.
     */
    public function warn($message, array $context = array())
    {
        @trigger_error('The '.__METHOD__.' method inherited from the Symfony\Component\HttpKernel\Log\LoggerInterface interface is deprecated since version 2.2 and will be removed in 3.0. Use the warning() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);

        return parent::addRecord(BaseLogger::WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->getLogs();
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors()
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->countErrors();
        }

        return 0;
    }

    /**
     * Returns a DebugLoggerInterface instance if one is registered with this logger.
     *
     * @return DebugLoggerInterface|null A DebugLoggerInterface instance or null if none is registered
     */
    private function getDebugLogger()
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
        }
    }
}
