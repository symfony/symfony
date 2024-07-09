<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Unifonic;

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
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
final class UnifonicTransport extends AbstractTransport
{
    protected const HOST = 'el.cloud.unifonic.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $appSid,
        private readonly ?string $from = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('unifonic://%s%s', $this->getEndpoint(), null !== $this->from ? '?from='.$this->from : '');
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

        $endpoint = \sprintf('https://%s/rest/SMS/messages', $this->getEndpoint());

        $body = [
            'AppSid' => $this->appSid,
            'Body' => $message->getSubject(),
            'Recipient' => $message->getPhone(),
        ];

        if ('' !== $message->getFrom()) {
            $body['SenderID'] = $message->getFrom();
        } elseif (null !== $this->from) {
            $body['SenderID'] = $this->from;
        }

        $response = $this->client->request('POST', $endpoint, [
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException(\sprintf('Could not reach "%s" endpoint.', $endpoint), $response, previous: $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Unable to send SMS.', $response);
        }

        $content = $response->toArray(false);

        if ('true' != $content['success']) {
            throw new TransportException(\sprintf('Unable to send the SMS. Reason: "%s". Error code: "%s".', $content['message'], $content['errorCode']), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
