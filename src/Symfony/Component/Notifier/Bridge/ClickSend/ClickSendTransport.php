<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ClickSend;

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
final class ClickSendTransport extends AbstractTransport
{
    protected const HOST = 'rest.clicksend.com';

    public function __construct(
        private readonly string $apiUsername,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $from = null,
        private readonly ?string $source = null,
        private readonly ?string $listId = null,
        private readonly ?string $fromEmail = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $query = array_filter([
            'from' => $this->from,
            'source' => $this->source,
            'list_id' => $this->listId,
            'from_email' => $this->fromEmail,
        ]);

        return \sprintf('clicksend://%s%s', $this->getEndpoint(), $query ? '?'.http_build_query($query, '', '&') : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof ClickSendOptions);
    }

    /**
     * https://developers.clicksend.com/docs/rest/v3/#send-sms.
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = \sprintf('https://%s/v3/sms/send', $this->getEndpoint());

        $options = $message->getOptions()?->toArray() ?? [];
        $options['body'] = $message->getSubject();
        $options['from'] = $message->getFrom() ?: $this->from;
        $options['source'] ??= $this->source;
        $options['list_id'] ??= $this->listId;
        $options['from_email'] ?? $this->fromEmail;

        if (isset($options['from']) && !preg_match('/^[a-zA-Z0-9\s]{3,11}$/', $options['from']) && !preg_match('/^\+[1-9]\d{1,14}$/', $options['from'])) {
            throw new InvalidArgumentException(\sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $options['from']));
        }

        if ($options['list_id'] ?? false) {
            $options['to'] = $message->getPhone();
        }

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->apiUsername, $this->apiKey],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote ClickSend server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->getContent(false);
            throw new TransportException(\sprintf('Unable to send the SMS - "%s".', $error ?: 'unknown failure'), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
