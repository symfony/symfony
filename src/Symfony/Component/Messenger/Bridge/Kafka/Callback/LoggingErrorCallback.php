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
use RdKafka\KafkaConsumer;

final class LoggingErrorCallback
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(KafkaConsumer $kafka, int $err, string $reason): void
    {
        $this->logger->error($reason, [
            'error_code' => $err,
        ]);
    }
}
