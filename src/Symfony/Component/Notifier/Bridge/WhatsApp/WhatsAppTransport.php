<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppTransport extends AbstractTransport
{
    protected const HOST = 'graph.facebook.com';

    public function __construct(
        #[\SensitiveParameter] private string $accessToken,
        private string $phoneNumberId,
        private string $apiVersion,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('whatsapp://%s?phone_number_id=%s&api_version=%s', $this->getEndpoint(), $this->phoneNumberId, $this->apiVersion);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof WhatsAppOptions)
            && '' !== ($message->getRecipientId() ?? '');
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions();
        $options = $options instanceof WhatsAppOptions ? $options : null;
        $to = $message->getRecipientId();

        if (null === $to || '' === $to) {
            throw new InvalidArgumentException('Unable to send a WhatsApp message without a recipient phone number set via WhatsAppOptions::recipientPhoneNumber().');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            ...$this->messageBody($options, $message->getSubject()),
        ];

        $endpoint = \sprintf('%s://%s/%s/%s/messages', $this->getHttpScheme(), $this->getEndpoint(), $this->apiVersion, $this->phoneNumberId);

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->accessToken,
            'json' => $payload,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Meta WhatsApp Cloud API server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            try {
                $errorMessage = $response->toArray(false)['error']['message'] ?? null;
            } catch (DecodingExceptionInterface) {
                $errorMessage = $response->getContent(false);
            }

            throw new TransportException(\sprintf('Unable to send the WhatsApp message: error %d ("%s").', $statusCode, $errorMessage ?: 'unknown error'), $response);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Unable to send the WhatsApp message: malformed response from the WhatsApp Cloud API.', $response, 0, $e);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((string) ($content['messages'][0]['id'] ?? ''));

        return $sentMessage;
    }

    /**
     * @return array{type: string, template?: array<string, mixed>, text?: array{body: string}}
     */
    private function messageBody(?WhatsAppOptions $options, string $subject): array
    {
        $template = $options?->getTemplate();

        if (null === $template) {
            return [
                'type' => 'text',
                'text' => ['body' => $subject],
            ];
        }

        $parameters = array_map(
            static fn (string $value): array => ['type' => 'text', 'text' => $value],
            $template['bodyParameters'],
        );

        return [
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => ['code' => $template['languageCode']],
                'components' => [] === $parameters ? [] : [
                    ['type' => 'body', 'parameters' => $parameters],
                ],
            ],
        ];
    }
}
