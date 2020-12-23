<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendinblue;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Pierre Tondereau <pierre.tondereau@gmail.com>
 */
final class SendinblueTransport extends AbstractTransport
{
    protected const HOST = 'api.sendinblue.com';

    private $apiKey;
    private $sender;

    public function __construct(string $apiKey, string $sender, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->sender = $sender;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('sendinblue://%s?sender=%s', $this->getEndpoint(), $this->sender);
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

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v3/transactionalSMS/sms', [
            'json' => [
                'sender' => $this->sender,
                'recipient' => $message->getPhone(),
                'content' => $message->getSubject(),
            ],
            'headers' => [
                'api-key' => $this->apiKey,
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.$error['message'], $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['messageId']);

        return $sentMessage;
    }
}
