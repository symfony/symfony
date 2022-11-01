<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FortySixElks;

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
 * @author Jon Gotlin <jon@jon.se>
 */
final class FortySixElksTransport extends AbstractTransport
{
    protected const HOST = 'api.46elks.com';

    private string $apiUsername;
    private string $apiPassword;
    private string $from;

    public function __construct(string $apiUsername, #[\SensitiveParameter] string $apiPassword, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('forty-six-elks://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/a1/sms', self::HOST);
        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'from' => $from,
                'to' => $message->getPhone(),
                'message' => $message->getSubject(),
            ],
            'auth_basic' => [$this->apiUsername, $this->apiPassword],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote 46elks server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Unable to post the 46elks message: '.$response->getContent(false), $response);
        }

        $result = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id'] ?? '');

        return $sentMessage;
    }
}
