<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * @author Mathias STRASSER <contact@roukmoute.fr>
 *
 * @experimental in 4.2
 */
final class ForceConsumptionReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $logger;

    public function __construct(ReceiverInterface $decoratedReceiver, LoggerInterface $logger = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->logger = $logger;
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(
            function (?Envelope $envelope) use ($handler) {
                try {
                    $handler($envelope);
                } catch (\Throwable $exception) {
                    if (null === $this->logger) {
                        return;
                    }

                    $this->logger->alert(
                        'Receiver reached an exception: "{message}"',
                        array('message' => $exception->getMessage())
                    );
                }
            }
        );
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
