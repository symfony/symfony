<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Log;

use Monolog\Logger;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugLoggerConfigurator
{
    private ?DebugLoggerInterface $processor = null;

    public function __construct(DebugLoggerInterface $processor, bool $enable = null)
    {
        if ($enable ?? !\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            $this->processor = $processor;
        }
    }

    public function pushDebugLogger(Logger $logger): void
    {
        if ($this->processor) {
            $logger->pushProcessor($this->processor);
        }
    }

    public static function getDebugLogger(mixed $logger): ?DebugLoggerInterface
    {
        if ($logger instanceof DebugLoggerInterface) {
            return $logger;
        }

        if (!$logger instanceof Logger) {
            return null;
        }

        foreach ($logger->getProcessors() as $processor) {
            if ($processor instanceof DebugLoggerInterface) {
                return $processor;
            }
        }

        return null;
    }
}
