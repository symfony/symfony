<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sms77;

trigger_deprecation('symfony/sms77-notifier', '7.2', 'The "symfony/sms77-notifier" package is deprecated, use "symfony/sevenio-notifier" instead.');

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
 * @author André Matthies <matthiez@gmail.com>
 *
 * @deprecated since Symfony 7.2, use the Seven.io bridge instead.
 */
final class Sms77Transport extends AbstractTransport
{
    protected const HOST = 'gateway.sms77.io';

    public function __construct(
        #[\SensitiveParameter] private string $apiKey,
        private ?string $from = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('sms77://%s%s', $this->getEndpoint(), null !== $this->from ? '?from='.$this->from : '');
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

        $endpoint = \sprintf('https://%s/api/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'SentWith' => 'Symfony Notifier',
                'X-Api-Key' => $this->apiKey,
            ],
            'json' => [
                'from' => $message->getFrom() ?: $this->from,
                'json' => 1,
                'text' => $message->getSubject(),
                'to' => $message->getPhone(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Sms77 server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $error['description'], $error['code']), $response);
        }

        $success = $response->toArray(false);

        if (false === \in_array($success['success'], [100, 101])) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s".', $success['success']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((int) $success['messages'][0]['id']);

        return $sentMessage;
    }
}
