<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mastodon;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 *
 * @see https://docs.joinmastodon.org
 */
final class MastodonTransport extends AbstractTransport
{
    public function __construct(#[\SensitiveParameter] private readonly string $accessToken, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('mastodon://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof MastodonOptions);
    }

    public function request(string $method, string $url, array $options): ResponseInterface
    {
        $url = sprintf('https://%s%s', $this->getEndpoint(), $url);

        $options['auth_bearer'] = $this->accessToken;

        return $this->client->request($method, $url, $options);
    }

    /**
     * @see https://docs.joinmastodon.org/methods/statuses/
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['status'] = $message->getSubject();
        $response = null;

        try {
            if (isset($options['attach'])) {
                $options['media_ids'] = $this->uploadMedia($options['attach']);
                unset($options['attach']);
            }

            $response = $this->request('POST', '/api/v1/statuses', ['json' => $options]);
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            if (null !== $response) {
                throw new TransportException($e->getMessage(), $response, 0, $e);
            }

            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(sprintf('Unable to post the Mastodon message: "%s" (%s).', $result['error_description'], $result['error']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id']);

        return $sentMessage;
    }

    /**
     * @param array<array{file: File, thumbnail: File|null, description: string|null, focus: string}> $media
     */
    private function uploadMedia(array $media): array
    {
        $responses = [];

        foreach ($media as [
            'file' => $file,
            'thumbnail' => $thumbnail,
            'description' => $description,
            'focus' => $focus,
        ]) {
            $formDataPart = new FormDataPart(array_filter([
                'file' => new DataPart($file),
                'thumbnail' => $thumbnail ? new DataPart($thumbnail) : null,
                'description' => $description,
                'focus' => $focus,
            ]));

            $headers = [];
            foreach ($formDataPart->getPreparedHeaders()->all() as $header) {
                $headers[] = $header->toString();
            }

            $responses[] = $this->request('POST', '/api/v2/media', [
                'headers' => $headers,
                'body' => $formDataPart->bodyToIterable(),
            ]);
        }

        $mediaIds = [];

        try {
            foreach ($responses as $i => $response) {
                unset($responses[$i]);
                $result = $response->toArray(false);

                if (300 <= $response->getStatusCode()) {
                    throw new TransportException(sprintf('Unable to upload media as attachment: "%s" (%s).', $result['error_description'], $result['error']), $response);
                }

                $mediaIds[] = $result['id'];
            }
        } finally {
            foreach ($responses as $response) {
                $response->cancel();
            }
        }

        return $mediaIds;
    }
}
