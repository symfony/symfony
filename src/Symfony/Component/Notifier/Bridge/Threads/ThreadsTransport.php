<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads;

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
 * Publishes to Threads via the Threads Graph API (container → publish).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 *
 * @see https://developers.facebook.com/docs/threads/posts
 */
final class ThreadsTransport extends AbstractTransport
{
    protected const HOST = 'graph.threads.net';

    public const MAX_TEXT_LENGTH = 500;

    public const POLL_ATTEMPTS = 30;

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
        return \sprintf('threads://%s?user_id=%s&api_version=%s', $this->getEndpoint(), $this->userId, $this->apiVersion);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof ThreadsOptions);
    }

    /**
     * @return array{id: string, status: string, error_message: ?string, raw: array<string, mixed>, response: ResponseInterface}
     */
    private function getContainerStatus(string $containerId): array
    {
        $containerId = trim($containerId);
        if ('' === $containerId) {
            throw new InvalidArgumentException('Threads container id is empty.');
        }

        $response = $this->client->request('GET', $this->graphUrl($containerId), [
            'auth_bearer' => $this->accessToken,
            'query' => [
                'fields' => 'id,status,error_message',
            ],
        ]);

        $data = $this->decodeSuccessfulResponse($response, 'Unable to fetch the Threads container status');

        $status = isset($data['status']) && \is_string($data['status']) ? strtoupper($data['status']) : 'UNKNOWN';
        $errorMessage = isset($data['error_message']) && \is_string($data['error_message']) ? $data['error_message'] : null;
        $id = isset($data['id']) && \is_string($data['id']) ? $data['id'] : $containerId;

        return [
            'id' => $id,
            'status' => $status,
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

        if (($options = $message->getOptions()) && !$options instanceof ThreadsOptions) {
            throw new UnsupportedOptionsException(__CLASS__, ThreadsOptions::class, $options);
        }

        $options ??= new ThreadsOptions();
        $text = trim($message->getSubject());
        $mediaType = $options->getMediaType();

        if (ThreadsOptions::MEDIA_TYPE_TEXT === $mediaType && '' === $text) {
            throw new InvalidArgumentException('Threads text posts require a non-empty message subject.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Threads text exceeds the %d character limit.', self::MAX_TEXT_LENGTH));
        }

        $createBody = $options->toArray();
        if ('' !== $text) {
            $createBody['text'] = $text;
        }

        if (ThreadsOptions::MEDIA_TYPE_IMAGE === $mediaType) {
            $this->assertHttpsUrl($createBody['image_url'] ?? '', 'image_url');
        }

        if (ThreadsOptions::MEDIA_TYPE_VIDEO === $mediaType) {
            $this->assertHttpsUrl($createBody['video_url'] ?? '', 'video_url');
        }

        $createResponse = $this->client->request('POST', $this->graphUrl($this->userId.'/threads'), [
            'auth_bearer' => $this->accessToken,
            'body' => $createBody,
        ]);
        $created = $this->decodeSuccessfulResponse($createResponse, 'Unable to create the Threads media container');
        $containerId = isset($created['id']) && \is_string($created['id']) ? $created['id'] : '';
        if ('' === $containerId) {
            throw new TransportException('Unable to create the Threads media container: missing container id.', $createResponse);
        }

        if (ThreadsOptions::MEDIA_TYPE_TEXT !== $mediaType) {
            $this->waitUntilContainerReady($containerId);
        }

        $publishResponse = $this->client->request('POST', $this->graphUrl($this->userId.'/threads_publish'), [
            'auth_bearer' => $this->accessToken,
            'body' => [
                'creation_id' => $containerId,
            ],
        ]);
        $published = $this->decodeSuccessfulResponse($publishResponse, 'Unable to publish the Threads container');
        $postId = isset($published['id']) && \is_string($published['id']) ? $published['id'] : '';
        if ('' === $postId) {
            throw new TransportException('Unable to publish the Threads container: missing post id.', $publishResponse);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($postId);

        return $sentMessage;
    }

    private function waitUntilContainerReady(string $containerId): void
    {
        $status = null;
        for ($attempt = 0; $attempt < $this->pollAttempts; ++$attempt) {
            $status = $this->getContainerStatus($containerId);
            $code = $status['status'];

            if (\in_array($code, ['FINISHED', 'PUBLISHED'], true)) {
                return;
            }

            if (\in_array($code, ['ERROR', 'EXPIRED'], true)) {
                throw new TransportException(\sprintf('Threads container processing failed (status "%s"): "%s"', $code, $status['error_message'] ?? 'unknown error'), $status['response']);
            }

            // let the client drive the wait when it can, so the loop does not block the process
            ($status['response']->getInfo('pause_handler') ?? sleep(...))($this->pollDelay);
        }

        $status ??= $this->getContainerStatus($containerId);

        throw new TransportException(\sprintf('Threads container "%s" did not finish processing in time.', $containerId), $status['response']);
    }

    private function assertHttpsUrl(string $url, string $field): void
    {
        if ('' === trim($url) || !str_starts_with(strtolower($url), 'https://')) {
            throw new InvalidArgumentException(\sprintf('Threads "%s" must be a public HTTPS URL.', $field));
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
            throw new TransportException('Could not reach the remote Threads Graph API server.', $response, 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException(\sprintf('%s: %s', $failurePrefix, $this->formatGraphError($response, $statusCode)), $response);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException(\sprintf('%s: malformed response from the Threads Graph API.', $failurePrefix), $response, 0, $e);
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
