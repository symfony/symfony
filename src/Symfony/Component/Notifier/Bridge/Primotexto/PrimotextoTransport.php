<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Samaël Tomas <samael.tomas@gmail.com>
 */
final class PrimotextoTransport extends AbstractTransport
{
    protected const HOST = 'api.primotexto.com';

    public function __construct(
        #[\SensitiveParameter]
        private string $apiKey,
        private ?string $from = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['from'] = $message->getFrom() ?: $this->from;
        $options['number'] = $message->getPhone();
        $options['message'] = $message->getSubject();

        $endpoint = \sprintf('https://%s/v2/notification/messages/send', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'X-Primotexto-ApiKey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Primotexto server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            $errorCodeValue = PrimotextoErrorCode::tryFrom((int) $error['code']) ?? PrimotextoErrorCode::UNKNOWN_ERROR;

            throw new TransportException(\sprintf('Unable to send the SMS, error "%s" : "%s".', $error['code'], $errorCodeValue->name), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['snapshotId']);

        return $sentMessage;
    }

    public function __toString(): string
    {
        return \sprintf('primotexto://%s%s', $this->getEndpoint(), null !== $this->from ? '?from='.$this->from : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof PrimotextoOptions);
    }
}
