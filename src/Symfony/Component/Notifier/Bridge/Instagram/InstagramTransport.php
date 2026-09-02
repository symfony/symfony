<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Publishes Instagram media via Instagram API with Instagram Login (container → publish).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 *
 * @see https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/content-publishing
 */
final class InstagramTransport extends AbstractTransport
{
    protected const HOST = 'graph.instagram.com';

    public const POLL_ATTEMPTS = 45;

    public const POLL_DELAY_SECONDS = 2;

    public function __construct(
        #[\SensitiveParameter] private string $accessToken,
        private string $userId,
        private string $apiVersion,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        private int $pollAttempts = self::POLL_ATTEMPTS,
        private float $pollDelay = self::POLL_DELAY_SECONDS,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('instagram://%s?user_id=%s&api_version=%s', $this->getEndpoint(), $this->userId, $this->apiVersion);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof InstagramOptions);
    }

    /**
     * @return array{id: string, status_code: string, error_message: ?string, raw: array<string, mixed>, response: ResponseInterface}
     */
    private function getContainerStatus(string $containerId): array
    {
        $containerId = trim($containerId);
        if ('' === $containerId) {
            throw new InvalidArgumentException('Instagram container id is empty.');
        }

        $response = $this->client->request('GET', $this->graphUrl($containerId), [
            'auth_bearer' => $this->accessToken,
            'query' => [
                'fields' => 'id,status_code,status,error_message',
            ],
        ]);

        $data = $this->decodeSuccessfulResponse($response, 'Unable to fetch the Instagram container status');

        $statusCode = isset($data['status_code']) && \is_string($data['status_code'])
            ? strtoupper($data['status_code'])
            : (isset($data['status']) && \is_string($data['status']) ? strtoupper($data['status']) : 'UNKNOWN');
        $errorMessage = isset($data['error_message']) && \is_string($data['error_message']) ? $data['error_message'] : null;
        $id = isset($data['id']) && \is_string($data['id']) ? $data['id'] : $containerId;

        return [
            'id' => $id,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
            'raw' => $data,
            'response' => $response,
        ];
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (($options = $message->getOptions()) && !$options instanceof InstagramOptions) {
            throw new UnsupportedOptionsException(__CLASS__, InstagramOptions::class, $options);
        }

        $options ??= new InstagramOptions();
        $caption = trim($message->getSubject());
        $mediaType = $options->getMediaType();

        $createBody = $options->toArray();
        if ('' !== $caption) {
            $createBody['caption'] = $caption;
        }

        if (InstagramOptions::MEDIA_TYPE_REELS === $mediaType) {
            $this->assertHttpsUrl($createBody['video_url'] ?? '', 'video_url');
        } else {
            $this->assertHttpsUrl($createBody['image_url'] ?? '', 'image_url');
        }

        $createResponse = $this->client->request('POST', $this->graphUrl($this->userId.'/media'), [
            'auth_bearer' => $this->accessToken,
            'body' => $createBody,
        ]);
        $created = $this->decodeSuccessfulResponse($createResponse, 'Unable to create the Instagram media container');
        $containerId = isset($created['id']) && \is_string($created['id']) ? $created['id'] : '';
        if ('' === $containerId) {
            throw new TransportException('Unable to create the Instagram media container: missing container id.', $createResponse);
        }

        $this->waitUntilContainerReady($containerId);

        $publishResponse = $this->client->request('POST', $this->graphUrl($this->userId.'/media_publish'), [
            'auth_bearer' => $this->accessToken,
            'body' => [
                'creation_id' => $containerId,
            ],
        ]);
        $published = $this->decodeSuccessfulResponse($publishResponse, 'Unable to publish the Instagram container');
        $mediaId = isset($published['id']) && \is_string($published['id']) ? $published['id'] : '';
        if ('' === $mediaId) {
            throw new TransportException('Unable to publish the Instagram container: missing media id.', $publishResponse);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($mediaId);

        return $sentMessage;
    }

    private function waitUntilContainerReady(string $containerId): void
    {
        $status = null;
        for ($attempt = 0; $attempt < $this->pollAttempts; ++$attempt) {
            $status = $this->getContainerStatus($containerId);
            $code = $status['status_code'];

            if (\in_array($code, ['FINISHED', 'PUBLISHED'], true)) {
                return;
            }

            if (\in_array($code, ['ERROR', 'EXPIRED'], true)) {
                throw new TransportException(\sprintf('Instagram container processing failed (status "%s"): "%s"', $code, $status['error_message'] ?? 'unknown error'), $status['response']);
            }

            // let the client drive the wait when it can, so the loop does not block the process
            ($status['response']->getInfo('pause_handler') ?? sleep(...))($this->pollDelay);
        }

        $status ??= $this->getContainerStatus($containerId);

        throw new TransportException(\sprintf('Instagram container "%s" did not finish processing in time.', $containerId), $status['response']);
    }

    private function assertHttpsUrl(string $url, string $field): void
    {
        if ('' === trim($url) || !str_starts_with(strtolower($url), 'https://')) {
            throw new InvalidArgumentException(\sprintf('Instagram "%s" must be a public HTTPS URL.', $field));
        }
    }

    private function graphUrl(string $path): string
    {
        return \sprintf('%s://%s/%s/%s', $this->getHttpScheme(), $this->getEndpoint(), $this->apiVersion, ltrim($path, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSuccessfulResponse(ResponseInterface $response, string $failurePrefix): array
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Instagram Graph API server.', $response, 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException(\sprintf('%s: %s', $failurePrefix, $this->formatGraphError($response, $statusCode)), $response);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException(\sprintf('%s: malformed response from the Instagram Graph API.', $failurePrefix), $response, 0, $e);
        }

        return $content;
    }

    private function formatGraphError(ResponseInterface $response, int $statusCode): string
    {
        try {
            $data = $response->toArray(false);
            $error = $data['error'] ?? null;
            if (\is_array($error)) {
                $message = isset($error['message']) && \is_string($error['message']) ? $error['message'] : 'unknown error';
                $code = $error['code'] ?? null;
                $subcode = $error['error_subcode'] ?? null;
                $trace = isset($error['fbtrace_id']) && \is_string($error['fbtrace_id']) ? $error['fbtrace_id'] : null;

                return \sprintf(
                    'HTTP %d, code %s, subcode %s, fbtrace_id %s ("%s")',
                    $statusCode,
                    null !== $code ? (string) $code : 'n/a',
                    null !== $subcode ? (string) $subcode : 'n/a',
                    $trace ?? 'n/a',
                    $message,
                );
            }
        } catch (DecodingExceptionInterface) {
            // Fall through.
        }

        try {
            $raw = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            $raw = '';
        }

        return \sprintf('HTTP %d ("%s")', $statusCode, '' !== $raw ? $raw : 'unknown error');
    }
}
