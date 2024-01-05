<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Cesur APAYDIN <https://github.com/cesurapp>
 */
final class FirebaseJwtTransport extends AbstractTransport
{
    protected const HOST = 'fcm.googleapis.com/v1/projects/project_id/messages:send';

    private array $credentials;

    public function __construct(#[\SensitiveParameter] array $credentials, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->credentials = $credentials;
        $this->client = $client;

        $this->setHost(str_replace('project_id', $credentials['project_id'], $this->getDefaultHost()));

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('firebase-jwt://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof FirebaseOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());
        $options = $message->getOptions()?->toArray() ?? [];
        $options['token'] = $message->getRecipientId();
        unset($options['to']);

        if (!$options['token']) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "to" option to be set.', __CLASS__));
        }
        $options['notification']['body'] = $message->getSubject();
        $options['data'] ??= [];

        // Send
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->getJwtToken()),
            ],
            'json' => array_filter(['message' => $options]),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Firebase server.', $response, 0, $e);
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $jsonContents = str_starts_with($contentType, 'application/json') ? $response->toArray(false) : null;
        $errorMessage = null;

        if ($jsonContents && isset($jsonContents['results'][0]['error'])) {
            $errorMessage = $jsonContents['results'][0]['error'];
        } elseif (200 !== $statusCode) {
            $errorMessage = $response->getContent(false);
        }

        if (null !== $errorMessage) {
            throw new TransportException('Unable to post the Firebase message: ' . $errorMessage, $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string)$this);
        $sentMessage->setMessageId($success['results'][0]['message_id'] ?? '');

        return $sentMessage;
    }

    private function getJwtToken(): string
    {
        $time = time();
        $payload = [
            'iss' => $this->credentials['client_email'],
            'sub' => $this->credentials['client_email'],
            'aud' => 'https://fcm.googleapis.com/',
            'iat' => $time,
            'exp' => $time + 3600,
            'kid' => $this->credentials['private_key_id'],
        ];

        $header = $this->urlSafeEncode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = $this->urlSafeEncode($payload);
        openssl_sign($header . '.' . $payload, $signature, openssl_pkey_get_private($this->credentials['private_key']), OPENSSL_ALGO_SHA256);
        $signature = $this->urlSafeEncode($signature);

        return $header . '.' . $payload . '.' . $signature;
    }

    protected function urlSafeEncode($data): string
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        }

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
