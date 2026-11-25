<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Octopush;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends SMS through the legacy Octopush API (types "XXX", "FR", "WWW") or through
 * the Octopush API v1 (types "sms_low_cost", "sms_premium"), which also supports
 * a purpose ("alert" for transactional SMS, "wholesale" for marketing SMS).
 *
 * @author Aurélien Martin <pro@aurelienmartin.com>
 */
final class OctopushTransport extends AbstractTransport
{
    protected const HOST = 'www.octopush-dm.com';
    private const API_V1_HOST = 'api.octopush.com';
    private const API_V1_TYPES = ['sms_low_cost', 'sms_premium'];
    private const API_V1_PURPOSES = ['alert', 'wholesale'];

    public function __construct(
        private string $userLogin,
        #[\SensitiveParameter] private string $apiKey,
        private string $from,
        private string $type,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        private ?string $purpose = null,
    ) {
        if (null !== $this->purpose) {
            if (!$this->usesApiV1()) {
                throw new InvalidArgumentException(\sprintf('The Octopush "purpose" option requires one of the API v1 types "%s", "%s" given.', implode('", "', self::API_V1_TYPES), $this->type));
            }

            if (!\in_array($this->purpose, self::API_V1_PURPOSES, true)) {
                throw new InvalidArgumentException(\sprintf('The Octopush purpose must be one of "%s", "%s" given.', implode('", "', self::API_V1_PURPOSES), $this->purpose));
            }
        }

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $purpose = null !== $this->purpose ? '&purpose='.$this->purpose : '';

        return \sprintf('octopush://%s?from=%s&type=%s%s', $this->getEndpoint(), $this->from, $this->type, $purpose);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function getDefaultHost(): string
    {
        return $this->usesApiV1() ? self::API_V1_HOST : self::HOST;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        return $this->usesApiV1() ? $this->sendWithApiV1($message) : $this->sendWithLegacyApi($message);
    }

    private function usesApiV1(): bool
    {
        return \in_array($this->type, self::API_V1_TYPES, true);
    }

    private function sendWithLegacyApi(SmsMessage $message): SentMessage
    {
        $endpoint = \sprintf('%s://%s/api/sms/json', $this->getHttpScheme(), $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'user_login' => $this->userLogin,
                'api_key' => $this->apiKey,
                'sms_text' => $message->getSubject(),
                'sms_recipients' => $message->getPhone(),
                'sms_sender' => $message->getFrom() ?: $this->from,
                'sms_type' => $this->type,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Octopush server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.$error['error_code'], $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['ticket']);

        return $sentMessage;
    }

    private function sendWithApiV1(SmsMessage $message): SentMessage
    {
        $endpoint = \sprintf('%s://%s/v1/public/sms-campaign/send', $this->getHttpScheme(), $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                // The factory encodes the login for the form body of the legacy API
                'api-login' => urldecode($this->userLogin),
                'api-key' => $this->apiKey,
            ],
            'json' => [
                'recipients' => [['phone_number' => $message->getPhone()]],
                'text' => $message->getSubject(),
                'type' => $this->type,
                'purpose' => $this->purpose ?? 'alert',
                'sender' => $message->getFrom() ?: $this->from,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Octopush server.', $response, 0, $e);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException(\sprintf('Unable to send the SMS: unexpected response with status code %d.', $statusCode), $response, 0, $e);
        }

        if (201 !== $statusCode && 200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (code "%s").', $content['message'] ?? 'unknown error', $content['code'] ?? $statusCode), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($content['sms_ticket'] ?? '');

        return $sentMessage;
    }
}
