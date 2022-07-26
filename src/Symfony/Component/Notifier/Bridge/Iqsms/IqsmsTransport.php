<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Iqsms;

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
 * @author Oleksandr Barabolia <alexandrbarabolya@gmail.com>
 */
final class IqsmsTransport extends AbstractTransport
{
    protected const HOST = 'api.iqsms.ru';

    private string $login;
    private string $password;
    private string $from;

    public function __construct(string $login, #[\SensitiveParameter] string $password, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->password = $password;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('iqsms://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/messages/v2/send.json', [
            'json' => [
                'messages' => [
                    [
                        'phone' => $message->getPhone(),
                        'text' => $message->getSubject(),
                        'sender' => $from,
                        'clientId' => uniqid(),
                    ],
                ],
                'login' => $this->login,
                'password' => $this->password,
            ],
        ]);

        try {
            $result = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Iqsms server.', $response, 0, $e);
        }

        foreach ($result['messages'] as $msg) {
            if ('accepted' !== $msg['status']) {
                throw new TransportException(sprintf('Unable to send the SMS: "%s".', $msg['status']), $response);
            }
        }

        $message = new SentMessage($message, (string) $this);
        $message->setMessageId($result['messages'][0]['smscId']);

        return $message;
    }
}
