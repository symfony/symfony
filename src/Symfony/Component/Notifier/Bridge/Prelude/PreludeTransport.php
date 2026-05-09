<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Prelude;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Imad Zairig <imadzairig@gmail.com>
 */
final class PreludeTransport extends AbstractTransport
{
    protected const HOST = 'api.prelude.dev';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $sender,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('prelude://%s?sender=%s', $this->getEndpoint(), $this->sender);
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

        if (($options = $message->getOptions()) && !$options instanceof PreludeOptions) {
            throw new UnsupportedOptionsException(__CLASS__, PreludeOptions::class, $options);
        }

        $options = $options?->toArray() ?? [];

        $body = [
            'to' => $message->getPhone(),
        ];

        if ($this->sender) {
            $body['from'] = $this->sender;
        }

        if (!isset($options['template_id'])) {
            throw new LogicException(\sprintf('The "template_id" option is required for the "%s" transport.', __CLASS__));
        }

        $body = array_merge($body, $options);

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v2/notify', [
            'json' => $body,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $error) {
            throw new TransportException('Unable to send the SMS: '.($error->getMessage() ?: $response->getContent(false)), $response);
        }

        if (200 !== $statusCode && 201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.($error['message'] ?? $response->getContent(false)), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        if (isset($success['id'])) {
            $sentMessage->setMessageId($success['id']);
        }

        return $sentMessage;
    }
}
