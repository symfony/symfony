<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi;

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
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 */
final class GatewayApiTransport extends AbstractTransport
{
    protected const HOST = 'gatewayapi.com';

    public function __construct(
        #[\SensitiveParameter] private string $authToken,
        private string $from,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('gatewayapi://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof GatewayApiOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['sender'] = $message->getFrom() ?: $this->from;
        $options['recipients'] = [['msisdn' => $message->getPhone()]];
        $options['message'] = $message->getSubject();

        $endpoint = \sprintf('https://%s/rest/mtsms', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->authToken, ''],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote GatewayApi server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to send the SMS: error %d.', $statusCode), $response);
        }

        $content = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((string) $content['ids'][0]);

        return $sentMessage;
    }
}
