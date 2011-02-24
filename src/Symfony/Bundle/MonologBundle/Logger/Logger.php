<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Logger;

use Monolog\Logger as BaseLogger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * Logger.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
        $handler = $this->handler;
        while ($handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
            $handler = $handler->getParent();
        }

        return null;
    }

    public function log($message, $level)
    {
        return $this->addMessage($level, $message);
    }
}
