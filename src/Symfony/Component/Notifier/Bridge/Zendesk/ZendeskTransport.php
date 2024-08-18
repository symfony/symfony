<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zendesk;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class ZendeskTransport extends AbstractTransport
{
    public function __construct(
        private string $email,
        #[\SensitiveParameter] private string $token,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('zendesk://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof ZendeskOptions);
    }

    protected function doSend(?MessageInterface $message = null): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $endpoint = \sprintf('https://%s/api/v2/tickets.json', $this->getEndpoint());

        $body = [
            'ticket' => [
                'subject' => $message->getSubject(),
                'comment' => [
                    'body' => $message->getNotification()?->getContent() ?? '',
                ],
            ],
        ];

        $options = $message->getOptions()?->toArray() ?? [];
        if (isset($options['priority'])) {
            $body['ticket']['priority'] = $options['priority'];
        }

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->email.'/token', $this->token],
            'json' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Zendesk server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $result = $response->toArray(false);

            $errorMessage = $result['error'];
            if (\is_array($errorMessage)) {
                $errorMessage = implode(' | ', array_values($errorMessage));
            }

            throw new TransportException(\sprintf('Unable to post the Zendesk message: "%s".', $errorMessage), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
