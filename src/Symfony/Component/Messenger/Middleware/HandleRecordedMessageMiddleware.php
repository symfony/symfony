<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Exception\MessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RecordedMessageCollectionInterface;

/**
 * A middleware that takes all recorded messages and dispatch them to the bus.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class HandleRecordedMessageMiddleware implements MiddlewareInterface
{
    private $messageRecorder;
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus, RecordedMessageCollectionInterface $messageRecorder)
    {
        $this->messageRecorder = $messageRecorder;
        $this->messageBus = $messageBus;
    }

    public function handle($message, callable $next)
    {
        // Make sure the recorder is empty before we begin
        $this->messageRecorder->resetRecordedMessages();

        try {
            $returnData = $next($message);
        } catch (\Throwable $exception) {
            $this->messageRecorder->resetRecordedMessages();

            throw $exception;
        }

        $exceptions = array();
        while (!empty($recordedMessages = $this->messageRecorder->getRecordedMessages())) {
            $this->messageRecorder->resetRecordedMessages();
            // Assert: The message recorder is empty, all messages are in $recordedMessages

            foreach ($recordedMessages as $recordedMessage) {
                try {
                    $this->messageBus->dispatch($recordedMessage);
                } catch (\Throwable $exception) {
                    $exceptions[] = $exception;
                }
            }
        }

        if (!empty($exceptions)) {
            if (1 === \count($exceptions)) {
                throw $exceptions[0];
            }
            throw new MessageHandlingException($exceptions);
        }

        return $returnData;
    }
}
