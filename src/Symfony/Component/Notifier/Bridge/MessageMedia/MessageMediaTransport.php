<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Adrian Nguyen <vuphuong87@gmail.com>
 */
final class MessageMediaTransport extends AbstractTransport
{
    protected const HOST = 'api.messagemedia.com';

    private string $apiKey;
    private string $apiSecret;
    private ?string $from;

    public function __construct(string $apiKey, #[\SensitiveParameter] string $apiSecret, string $from = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null !== $this->from) {
            return sprintf('messagemedia://%s?from=%s', $this->getEndpoint(), $this->from);
        }

        return sprintf('messagemedia://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        $endpoint = sprintf('https://%s/v1/messages', $this->getEndpoint());
        $response = $this->client->request(
            'POST',
            $endpoint,
            [
                'auth_basic' => $this->apiKey.':'.$this->apiSecret,
                'json' => [
                    'messages' => [
                        [
                            'destination_number' => $message->getPhone(),
                            'source_number' => $from,
                            'content' => $message->getSubject(),
                        ],
                    ],
                ],
            ]
        );

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote MessageMedia server.', $response, 0, $e);
        }

        if (202 === $statusCode) {
            $result = $response->toArray(false)['messages'][0];
            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($result['message_id']);

            return $sentMessage;
        }

        try {
            $error = $response->toArray(false);

            $errorMessage = $error['details'][0] ?? ($error['message'] ?? 'Unknown reason');
        } catch (DecodingExceptionInterface|TransportExceptionInterface) {
            $errorMessage = 'Unknown reason';
        }

        throw new TransportException(sprintf('Unable to send the SMS: "%s".', $errorMessage), $response);
    }
}
