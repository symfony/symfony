<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud;

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
 * @author Thomas Ferney <thomas.ferney@gmail.com>
 */
final class OvhCloudTransport extends AbstractTransport
{
    protected const HOST = 'eu.api.ovh.com';

    private string $applicationKey;
    private string $applicationSecret;
    private string $consumerKey;
    private string $serviceName;
    private ?string $sender = null;
    private bool $noStopClause = false;

    public function __construct(string $applicationKey, #[\SensitiveParameter] string $applicationSecret, #[\SensitiveParameter] string $consumerKey, string $serviceName, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->applicationKey = $applicationKey;
        $this->applicationSecret = $applicationSecret;
        $this->consumerKey = $consumerKey;
        $this->serviceName = $serviceName;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null !== $this->sender) {
            return sprintf('ovhcloud://%s?service_name=%s&sender=%s', $this->getEndpoint(), $this->serviceName, $this->sender);
        }

        return sprintf('ovhcloud://%s?service_name=%s', $this->getEndpoint(), $this->serviceName);
    }

    /**
     * @return $this
     */
    public function setNoStopClause(bool $noStopClause): static
    {
        $this->noStopClause = $noStopClause;

        return $this;
    }

    /**
     * @return $this
     */
    public function setSender(?string $sender): static
    {
        $this->sender = $sender;

        return $this;
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

        $endpoint = sprintf('https://%s/1.0/sms/%s/jobs', $this->getEndpoint(), $this->serviceName);

        $content = [
            'charset' => 'UTF-8',
            'class' => 'flash',
            'coding' => '8bit',
            'message' => $message->getSubject(),
            'receivers' => [$message->getPhone()],
            'noStopClause' => $this->noStopClause,
            'priority' => 'medium',
        ];

        if ('' !== $message->getFrom()) {
            $content['sender'] = $message->getFrom();
        } elseif ($this->sender) {
            $content['sender'] = $this->sender;
        } else {
            $content['senderForResponse'] = true;
        }

        $now = time() + $this->calculateTimeDelta();
        $headers['X-Ovh-Application'] = $this->applicationKey;
        $headers['X-Ovh-Timestamp'] = $now;
        $headers['Content-Type'] = 'application/json';

        $body = json_encode($content, \JSON_UNESCAPED_SLASHES);
        $toSign = $this->applicationSecret.'+'.$this->consumerKey.'+POST+'.$endpoint.'+'.$body.'+'.$now;
        $headers['X-Ovh-Consumer'] = $this->consumerKey;
        $headers['X-Ovh-Signature'] = '$1$'.sha1($toSign);

        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote OvhCloud server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: %s.', $error['message']), $response);
        }

        $success = $response->toArray(false);

        if (!isset($success['ids'][0])) {
            throw new TransportException(sprintf('Attempt to send the SMS to invalid receivers: "%s".', implode(',', $success['invalidReceivers'])), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['ids'][0]);

        return $sentMessage;
    }

    /**
     * Calculates the time delta between the local machine and the API server.
     */
    private function calculateTimeDelta(): int
    {
        $endpoint = sprintf('https://%s/1.0/auth/time', $this->getEndpoint());
        $response = $this->client->request('GET', $endpoint);

        return $response->getContent() - time();
    }
}
