<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
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
 * @author gnito-org <https://github.com/gnito-org>
 */
final class BandwidthTransport extends AbstractTransport
{
    protected const HOST = 'messaging.bandwidth.com';

    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $from,
        private readonly string $accountId,
        private readonly string $applicationId,
        private readonly ?string $priority,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('bandwidth://%s?from=%s&account_id=%s&application_id=%s', $this->getEndpoint(), $this->from, $this->accountId, $this->applicationId).($this->priority ? sprintf('&priority=%s', $this->priority) : null);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * https://dev.bandwidth.com/apis/messaging/#tag/Messages/operation/createMessage.
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }
        $opts = $message->getOptions();
        $options = $opts ? $opts->toArray() : [];
        $options['text'] = $message->getSubject();
        $options['from'] = $options['from'] ?? $this->from;
        $options['to'] = $options['to'] ?? [$message->getPhone()];
        $options['account_id'] = $options['account_id'] ?? $this->accountId;
        $options['applicationId'] = $options['application_id'] ?? $this->applicationId;
        unset($options['application_id']);

        if (!isset($options['priority']) && $this->priority) {
            $options['priority'] = $this->priority;
        }

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $this->from)) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number. The number must be in E.164 format.', $this->from));
        }

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $message->getPhone())) {
            throw new InvalidArgumentException(sprintf('The "To" number "%s" is not a valid phone number. The number must be in E.164 format.', $message->getPhone()));
        }
        $endpoint = sprintf('https://%s/api/v2/users/%s/messages', $this->getEndpoint(), $options['account_id']);
        unset($options['accountId']);

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => $this->username.':'.$this->password,
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Bandwidth server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            $error = $response->toArray(false);
            throw new TransportException(sprintf('Unable to send the SMS - "%s" - "%s".', $error['type'], $error['description']), $response);
        }

        $success = $response->toArray(false);
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }
}
