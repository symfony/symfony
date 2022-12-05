<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SimpleTextin;

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
final class SimpleTextinTransport extends AbstractTransport
{
    protected const HOST = 'api-app2.simpletexting.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $from = null,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $queryParameters = [];
        if ($this->from) {
            $queryParameters['from'] = $this->from;
        }

        return sprintf('simpletextin://%s', $this->getEndpoint()).($queryParameters ? '?'.http_build_query($queryParameters) : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof SimpleTextinOptions);
    }

    /**
     * https://simpletexting.com/api/docs/v2/.
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }
        $endpoint = sprintf('https://%s/v2/api/messages', $this->getEndpoint());

        $opts = $message->getOptions();
        $options = $opts ? $opts->toArray() : [];
        $options['text'] = $message->getSubject();
        $options['contactPhone'] = $message->getPhone();
        $options['mode'] = 'AUTO';

        if (!isset($options['from']) && $this->from) {
            $options['from'] = $this->from;
        }

        if (isset($options['from']) && !preg_match('/^\+?[1-9]\d{1,14}$/', $options['from'])) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number.', $this->from));
        }

        if ($options['from'] ?? false) {
            $options['accountPhone'] = $options['from'];
            unset($options['from']);
        }

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->apiKey,
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SimpleTextin server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->getContent(false);
            throw new TransportException(sprintf('Unable to send the SMS - "%s".', $error ?: 'unknown failure'), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }
}
