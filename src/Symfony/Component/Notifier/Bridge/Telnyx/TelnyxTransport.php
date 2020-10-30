<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 */
final class TelnyxTransport extends AbstractTransport
{
    protected const HOST = 'api.telnyx.com';

    private $apiKey;
    private $from;

    public function __construct(string $apiKey, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('telnyx://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * @see https://developers.telnyx.com/docs/api/v2/messaging/Messages
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof TelnyxOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, TelnyxOptions::class));
        }

        $endpoint = sprintf('https://%s/v2/messages', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->apiKey,
            'json' => $this->preparePayload($message),
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $error['errors'][0]['title']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['data']['id']);

        return $sentMessage;
    }

    private function preparePayload(SmsMessage $message): array
    {
        $options = $message->getOptions() ? $message->getOptions()->toArray() : [];

        if (!isset($options['from'])) {
            $options['from'] = $this->from;
        }

        if (!isset($options['to'])) {
            $options['to'] = $message->getPhone();
        }

        if (!isset($options['text'])) {
            $options['text'] = $message->getSubject();
        }

        return $options;
    }
}
