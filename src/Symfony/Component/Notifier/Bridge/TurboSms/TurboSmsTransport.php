<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\TurboSms;

use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Artem Henvald <genvaldartem@gmail.com>
 *
 * @see https://turbosms.ua/api.html
 */
final class TurboSmsTransport extends AbstractTransport
{
    protected const HOST = 'api.turbosms.ua';

    private const SUBJECT_LATIN_LIMIT = 1521;
    private const SUBJECT_CYRILLIC_LIMIT = 661;
    private const SENDER_LIMIT = 20;

    private string $authToken;
    private string $from;

    public function __construct(#[\SensitiveParameter] string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->assertValidFrom($from);

        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('turbosms://%s?from=%s', $this->getEndpoint(), urlencode($this->from));
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

        $this->assertValidSubject($message->getSubject());

        $fromMessage = $message->getFrom();
        if (null !== $fromMessage) {
            $this->assertValidFrom($fromMessage);
            $from = $fromMessage;
        } else {
            $from = $this->from;
        }

        $endpoint = sprintf('https://%s/message/send.json', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->authToken,
            'json' => [
                'sms' => [
                    'sender' => $from,
                    'recipients' => [$message->getPhone()],
                    'text' => $message->getSubject(),
                ],
            ],
        ]);

        if (200 === $response->getStatusCode()) {
            $success = $response->toArray(false);

            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($success['response_result'][0]['message_id']);

            return $sentMessage;
        }

        $error = $response->toArray(false);

        throw new TransportException(sprintf('Unable to send SMS with TurboSMS: Error code %d with message "%s".', (int) $error['response_code'], $error['response_status']), $response);
    }

    private function assertValidFrom(string $from): void
    {
        if (mb_strlen($from, 'UTF-8') > self::SENDER_LIMIT) {
            throw new LengthException(sprintf('The sender length of a TurboSMS message must not exceed %d characters.', self::SENDER_LIMIT));
        }
    }

    private function assertValidSubject(string $subject): void
    {
        // Detect if there is at least one cyrillic symbol in the text
        if (1 === preg_match("/\p{Cyrillic}/u", $subject)) {
            $subjectLimit = self::SUBJECT_CYRILLIC_LIMIT;
            $symbols = 'cyrillic';
        } else {
            $subjectLimit = self::SUBJECT_LATIN_LIMIT;
            $symbols = 'latin';
        }

        if (mb_strlen($subject, 'UTF-8') > $subjectLimit) {
            throw new LengthException(sprintf('The subject length for "%s" symbols of a TurboSMS message must not exceed %d characters.', $symbols, $subjectLimit));
        }
    }
}
