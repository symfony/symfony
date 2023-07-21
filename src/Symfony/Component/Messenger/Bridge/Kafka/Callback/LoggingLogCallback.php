<?php

declare(strict_types=1);

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

final class LoggingLogCallback
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(object $kafka, int $level, string $facility, string $message): void
    {
        $this->logger->log(
            $level,
            $message,
            [
                'facility' => $facility,
            ],
        );
    }
}
