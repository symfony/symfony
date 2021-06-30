<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Octopush;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Aurélien Martin <pro@aurelienmartin.com>
 */
final class OctopushTransport extends AbstractTransport
{
    protected const HOST = 'www.octopush-dm.com';

    private $userLogin;
    private $apiKey;
    private $from;
    private $type;

    public function __construct(string $userLogin, string $apiKey, string $from, string $type, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->userLogin = $userLogin;
        $this->apiKey = $apiKey;
        $this->from = $from;
        $this->type = $type;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('octopush://%s?from=%s&type=%s', $this->getEndpoint(), $this->from, $this->type);
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

        $endpoint = sprintf('https://%s/api/sms/json', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'content_type' => 'multipart/form-data',
            ],
            'body' => [
                'user_login' => $this->userLogin,
                'api_key' => $this->apiKey,
                'sms_text' => $message->getSubject(),
                'sms_recipients' => $message->getPhone(),
                'sms_sender' => $this->from,
                'sms_type' => $this->type,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.$error['error_code'], $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['ticket']);

        return $sentMessage;
    }
}
