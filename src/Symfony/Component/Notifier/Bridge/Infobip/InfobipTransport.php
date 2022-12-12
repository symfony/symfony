<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip;

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
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class InfobipTransport extends AbstractTransport
{
    private string $authToken;
    private string $from;

    public function __construct(#[\SensitiveParameter] string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('infobip://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/sms/2/text/advanced', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'App '.$this->authToken,
            ],
            'json' => [
                'messages' => [
                    [
                        'from' => $from,
                        'destinations' => [
                            [
                                'to' => $message->getPhone(),
                            ],
                        ],
                        'text' => $message->getSubject(),
                    ],
                ],
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Infobip server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $content = $response->toArray(false);
            $errorMessage = $content['requestError']['serviceException']['messageId'] ?? '';
            $errorInfo = $content['requestError']['serviceException']['text'] ?? '';

            throw new TransportException(sprintf('Unable to send the SMS: '.$errorMessage.' (%s).', $errorInfo), $response);
        }

        return new SentMessage($message, (string) $this);
    }

    protected function getEndpoint(): string
    {
        return $this->host.($this->port ? ':'.$this->port : '');
    }
}
