<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird;

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
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class MessageBirdTransport extends AbstractTransport
{
    protected const HOST = 'rest.messagebird.com';

    private string $token;
    private string $from;

    public function __construct(#[\SensitiveParameter] string $token, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('messagebird://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/messages', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => 'AccessKey:'.$this->token,
            'body' => [
                'originator' => $from,
                'recipients' => $message->getPhone(),
                'body' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote MessageBird server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            if (!isset($response->toArray(false)['errors'])) {
                throw new TransportException('Unable to send the SMS.', $response);
            }

            $error = $response->toArray(false)['errors'];

            throw new TransportException('Unable to send the SMS: '.$error[0]['description'] ?? 'Unknown reason', $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        if (isset($success['id'])) {
            $sentMessage->setMessageId($success['id']);
        }

        return $sentMessage;
    }
}
