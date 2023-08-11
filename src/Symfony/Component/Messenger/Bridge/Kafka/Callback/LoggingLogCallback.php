<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Callback;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class LoggingLogCallback
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(object $kafka, int $level, string $facility, string $message): void
    {
        $this->logger->log(
            match ($level) {
                0 => LogLevel::EMERGENCY,
                1 => LogLevel::ALERT,
                2 => LogLevel::CRITICAL,
                3 => LogLevel::ERROR,
                4 => LogLevel::WARNING,
                5 => LogLevel::NOTICE,
                6 => LogLevel::INFO,
                7 => LogLevel::DEBUG,
                default => LogLevel::DEBUG,
            },
            $message,
            [
                'facility' => $facility,
            ],
        );
    }
}
