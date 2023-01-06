<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsBiuras;

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
final class SmsBiurasTransport extends AbstractTransport
{
    protected const HOST = 'savitarna.smsbiuras.lt';

    private string $uid;
    private string $apiKey;
    private string $from;
    private bool $testMode;

    private const ERROR_CODES = [
        1 => 'The message was processed and sent to the mobile operator. But delivery confirmations have not yet been returned.',
        2 => 'SMS not delivered.',
        3 => 'The SMS message was successfully delivered to the recipient.',
        4 => 'The message was sent and expired because it could not be delivered to the recipient during its validity period (48 hours according to our default platform).',
        5 => 'The message was received but the operator returned "Rejected" as the final status.',
        6 => 'Missing parameters, check that you are using all required parameters.',
        7 => ' Wrong apikey or uid.',
        8 => 'Sender ID - "from". Must be approved by an administrator.',
        9 => 'Balance insufficient, please top up the account.',
        10 => 'Bad date format for schedule parameter. Ex: urlencode("2021-03-11 12:00").',
        999 => 'Unknown Error',
    ];

    public function __construct(string $uid, #[\SensitiveParameter] string $apiKey, string $from, bool $testMode, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->uid = $uid;
        $this->apiKey = $apiKey;
        $this->from = $from;
        $this->testMode = $testMode;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if ($this->testMode) {
            return sprintf('smsbiuras://%s?from=%s&test_mode=%s', $this->getEndpoint(), $this->from, $this->testMode);
        }

        return sprintf('smsbiuras://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/api?', $this->getEndpoint());

        $response = $this->client->request('GET', $endpoint, [
            'query' => [
                'uid' => $this->uid,
                'apikey' => $this->apiKey,
                'message' => $message->getSubject(),
                'from' => $from,
                'test' => $this->testMode ? 1 : 0,
                'to' => $message->getPhone(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SmsBiuras server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Unable to send the SMS.', $response);
        }

        $matches = [];
        if (preg_match('/^ERROR: (\d+)$/', $response->getContent(), $matches)) {
            throw new TransportException('Unable to send the SMS: '.$this->getErrorMsg($matches[1] ?? 999), $response);
        }

        $matches = [];
        if (preg_match('/^OK: (\d+)$/', $response->getContent(), $matches)) {
            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($matches[1] ?? 0);

            return $sentMessage;
        }

        throw new TransportException('Unable to send the SMS.', $response);
    }

    private function getErrorMsg(int $errorCode): string
    {
        return self::ERROR_CODES[$errorCode] ?? self::ERROR_CODES[999];
    }
}
