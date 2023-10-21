<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Yunpian;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class YunpianTransport extends AbstractTransport
{
    protected const HOST = 'sms.yunpian.com';

    private string $apiKey;

    public function __construct(#[\SensitiveParameter] string $apiKey, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('yunpian://%s', $this->getEndpoint());
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

        if ('' !== $message->getFrom()) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not support "from" in "%s".', __CLASS__, SmsMessage::class));
        }

        $endpoint = sprintf('https://%s/v2/sms/single_send.json', self::HOST);
        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'apikey' => $this->apiKey,
                'mobile' => $message->getPhone(),
                'text' => $message->getSubject(),
            ],
        ]);

        try {
            $data = $response->toArray(false);
        } catch (ExceptionInterface) {
            throw new TransportException('Unable to send the SMS.', $response);
        }

        if (isset($data['code']) && 0 !== (int) $data['code']) {
            throw new TransportException(sprintf('Unable to send SMS: "Code: "%s". Message: "%s"".', $data['code'], $data['msg'] ?? 'Unknown reason'), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
