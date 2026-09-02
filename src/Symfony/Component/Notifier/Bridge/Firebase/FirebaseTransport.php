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

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
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
 * @author Jeroen Spee <https://github.com/Jeroeny>
 * @author Vojtech Smejkal <https://vojtechsmejkal.cz>
 *
 * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages/send
 */
final class FirebaseTransport extends AbstractTransport
{
    protected const HOST = 'fcm.googleapis.com';

    private string $jwt = '';
    private int $jwtExpiresAt = 0;

    public function __construct(
        #[\SensitiveParameter] private string $token,
        #[\SensitiveParameter] private string $projectId = '',
        #[\SensitiveParameter] private string $clientEmail = '',
        #[\SensitiveParameter] private string $privateKeyId = '',
        #[\SensitiveParameter] private string $privateKey = '',
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        if ('' !== $this->token) {
            trigger_deprecation('symfony/firebase-notifier', '8.2', 'The $token parameter in "%s" is deprecated, use $projectId, $clientEmail, $privateKeyId and $privateKey instead.', self::class);
        }

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('firebase://%s', $this->getEndpoint());
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

        if (!$this->projectId || !$this->privateKeyId || !$this->privateKey) {
            throw new IncompleteDsnException(\sprintf('The "%s" transport requires project_id, private_key_id and private_key options to be specified in DSN.', self::class));
        }

        $endpoint = \sprintf('%s://%s/v1/projects/%s/messages:send', $this->getHttpScheme(), $this->getEndpoint(), $this->projectId);

        $options = $message->getOptions()?->toArray() ?? [];
        $options['notification'] ??= [];
        $options['notification']['body'] = $message->getSubject();
        $options['data'] ??= [];

        if (!isset($options['token']) && !isset($options['topic']) && !isset($options['condition'])) {
            throw new InvalidArgumentException(\sprintf('The "%s" transport requires the "token", "topic" or "condition" option to be set.', self::class));
        }

        // Send
        $response = $this->client->request('POST', $endpoint, [
            'headers' => ['Authorization' => \sprintf('Bearer %s', $this->getJwt())],
            'json' => ['message' => $options],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Sending message to Firebase failed.', $response, 0, $e);
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $jsonContents = str_starts_with($contentType, 'application/json') ? $response->toArray(false) : null;
        $errorMessage = null;

        if (null !== $jsonContents && isset($jsonContents['error']['message'])) {
            $errorMessage = $jsonContents['error']['message'];
        } elseif (200 !== $statusCode) {
            $errorMessage = $response->getContent(false);
        }

        if (null !== $errorMessage) {
            throw new TransportException(\sprintf('Firebase server responded with error "%s"', $errorMessage), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['name'] ?? '');

        return $sentMessage;
    }

    private function getJwt(): string
    {
        // the assertion is valid for an hour, so it is reused until shortly before it expires
        if ($this->jwtExpiresAt > $time = time()) {
            return $this->jwt;
        }

        // "kid" is a JOSE header parameter: Google selects the signing key from it
        $header = $this->base64UrlEncode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->privateKeyId,
        ]);
        $payload = $this->base64UrlEncode([
            'iss' => $this->clientEmail,
            'sub' => $this->clientEmail,
            'aud' => 'https://fcm.googleapis.com/',
            'iat' => $time,
            'exp' => $time + 3600,
        ]);
        $key = openssl_pkey_get_private($this->privateKey);

        if (false === $key) {
            throw new InvalidArgumentException(\sprintf('The "%s" transport could not load private key from DSN. Is the key valid?', self::class));
        }

        openssl_sign($header.'.'.$payload, $signature, $key, \OPENSSL_ALGO_SHA256);

        $this->jwtExpiresAt = $time + 3540;

        return $this->jwt = $header.'.'.$payload.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @param string|array<string, string|int|float> $data
     */
    private function base64UrlEncode(string|array $data): string
    {
        if (\is_array($data)) {
            $data = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        }

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
