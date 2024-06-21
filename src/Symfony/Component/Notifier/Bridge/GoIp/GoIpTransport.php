<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoIp;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class GoIpTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $password,
        private readonly int $simSlot,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('goip://%s?sim_slot=%s', $this->getEndpoint(), $this->simSlot);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof GoIpOptions);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        if (($options = $message->getOptions()) && !$options instanceof GoIpOptions) {
            throw new LogicException(\sprintf('The "%s" transport only supports an instance of the "%s" as an option class.', __CLASS__, GoIpOptions::class));
        }

        if ('' !== $message->getFrom()) {
            throw new LogicException(\sprintf('The "%s" transport does not support the "From" option.', __CLASS__));
        }

        $response = $this->client->request('GET', $this->getEndpoint(), [
            'query' => [
                'u' => $this->username,
                'p' => $this->password,
                'l' => $options?->getSimSlot() ?? $this->simSlot,
                'n' => $message->getPhone(),
                'm' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the GoIP gateway.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('The GoIP gateway has responded with a wrong http_code: "%s" on the address: "%s".', $statusCode, $this->getEndpoint()), $response);
        }

        if (str_contains(strtolower($response->getContent()), 'error') || !str_contains(strtolower($response->getContent()), 'sending')) {
            throw new TransportException(\sprintf('Could not send the message through GoIP. Response: "%s".', $response->getContent()), $response);
        }

        if (!$messageId = $this->extractMessageIdFromContent($response->getContent())) {
            throw new TransportException(\sprintf('Could not extract the message id from the GoIP response: "%s".', $response->getContent()), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($messageId);

        return $sentMessage;
    }

    private function extractMessageIdFromContent(string $content): string|bool
    {
        preg_match('/; ID:(.*?)$/i', trim($content), $result);

        return $result[1] ?? false;
    }
}
