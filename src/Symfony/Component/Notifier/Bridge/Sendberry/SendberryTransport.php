<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendberry;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
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
final class SendberryTransport extends AbstractTransport
{
    protected const HOST = 'api.sendberry.com';

    private string $username;
    private string $password;
    private string $authKey;
    private string $from;

    public function __construct(string $username, #[\SensitiveParameter] string $password, string $authKey, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->authKey = $authKey;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('sendberry://%s:%s@%s?auth_key=%s&from=%s', $this->username, $this->password, $this->getEndpoint(), $this->authKey, $this->from);
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

        if (!preg_match('/^[+]+[1-9][0-9]{9,14}$/', $from)) {
            if ('' === $from) {
                throw new IncompleteDsnException('This phone number is invalid.');
            }

            if (!preg_match('/^[a-zA-Z0-9 ]+$/', $from)) {
                throw new IncompleteDsnException('The Sender ID is invalid.');
            }
        }

        $endpoint = sprintf('https://%s/SMS/SEND', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'json' => [
                'from' => $from,
                'to' => [$message->getPhone()],
                'content' => $message->getSubject(),
                'key' => $this->authKey,
                'name' => $this->username,
                'password' => $this->password,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Sendberry server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Unable to send the SMS.', $response);
        }

        $responseArr = $response->toArray();
        if (isset($responseArr['status']) && 'ok' !== $responseArr['status']) {
            throw new TransportException(sprintf("Unable to send the SMS. \n%s\n.", implode("\n", $responseArr['message'])), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        if (isset($responseArr['ID'])) {
            $sentMessage->setMessageId($responseArr['ID']);
        }

        return $sentMessage;
    }
}
