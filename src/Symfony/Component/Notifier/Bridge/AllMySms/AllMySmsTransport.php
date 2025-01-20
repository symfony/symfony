<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms;

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
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
final class AllMySmsTransport extends AbstractTransport
{
    protected const HOST = 'api.allmysms.com';

    public function __construct(
        private string $login,
        #[\SensitiveParameter] private string $apiKey,
        private ?string $from = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('allmysms://%s%s', $this->getEndpoint(), null !== $this->from ? '?from='.$this->from : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof AllMySmsOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['from'] = $message->getFrom() ?: $this->from;
        $options['to'] = $message->getPhone();
        $options['text'] = $message->getSubject();

        $endpoint = \sprintf('https://%s/sms/send/', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->login, $this->apiKey],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote AllMySms server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $error['description'], $error['code']), $response);
        }

        $success = $response->toArray(false);

        if (false === isset($success['smsId'])) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $success['description'], $success['code']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['smsId']);

        return $sentMessage;
    }
}
