<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twitter;

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
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TwitterTransport extends AbstractTransport
{
    protected const HOST = 'api.twitter.com';

    private static $nonce;

    private string $apiKey;
    private string $apiSecret;
    private string $accessToken;
    private string $accessSecret;

    public function __construct(#[\SensitiveParameter] string $apiKey, #[\SensitiveParameter] string $apiSecret, #[\SensitiveParameter] string $accessToken, #[\SensitiveParameter] string $accessSecret, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client, $dispatcher);

        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->accessToken = $accessToken;
        $this->accessSecret = $accessSecret;
    }

    public function __toString(): string
    {
        return sprintf('twitter://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof TwitterOptions);
    }

    public function request(string $method, string $url, array $options): ResponseInterface
    {
        $url = 'https://'.str_replace('api.', str_starts_with($url, '/1.1/media/') ? 'upload.' : 'api.', $this->getEndpoint()).$url;

        foreach (\is_array($options['body'] ?? null) ? $options['body'] : [] as $v) {
            if (!$v instanceof DataPart) {
                continue;
            }

            $formDataPart = new FormDataPart($options['body']);

            foreach ($formDataPart->getPreparedHeaders()->all() as $header) {
                $options['headers'][] = $header->toString();
            }

            $options['body'] = $formDataPart->bodyToIterable();

            break;
        }

        $oauth = [
            'oauth_consumer_key' => $this->apiKey,
            'oauth_nonce' => self::$nonce = hash('xxh128', self::$nonce ??= random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];

        $sign = $oauth + ($options['query'] ?? []) + (\is_array($options['body'] ?? null) ? $options['body'] : []);
        ksort($sign);

        $oauth['oauth_signature'] = base64_encode(hash_hmac(
            'sha1',
            implode('&', array_map('rawurlencode', [
                $method,
                $url,
                implode('&', array_map(fn ($k) => rawurlencode($k).'='.rawurlencode($sign[$k]), array_keys($sign))),
            ])),
            rawurlencode($this->apiSecret).'&'.rawurlencode($this->accessSecret),
            true
        ));

        $options['headers'][] = 'Authorization: OAuth '.implode(', ', array_map(fn ($k) => $k.'="'.rawurlencode($oauth[$k]).'"', array_keys($oauth)));

        return $this->client->request($method, $url, $options);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['text'] = $message->getSubject();
        $response = null;

        try {
            if (isset($options['attach'])) {
                $options['media']['media_ids'] = $this->uploadMedia($options['attach']);
                unset($options['attach']);
            }

            $response = $this->request('POST', '/2/tweets', ['json' => $options]);
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            if (null !== $response) {
                throw new TransportException($e->getMessage(), $response, 0, $e);
            }
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if (400 <= $statusCode) {
            throw new TransportException($result['title'].': '.($result['errors'][0]['message'] ?? $result['detail']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['data']['id']);

        return $sentMessage;
    }

    /**
     * @param array<array{file: File, alt: string, subtitles: File|null, category: string|null, owners: string[]}> $media
     */
    private function uploadMedia(array $media): array
    {
        $i = 0;
        $pool = [];

        foreach ($media as [
            'file' => $file,
            'alt' => $alt,
            'subtitles' => $subtitles,
            'category' => $category,
            'owners' => $extraOwners,
        ]) {
            $query = [
                'command' => 'INIT',
                'total_bytes' => $file->getSize(),
                'media_type' => $file->getContentType(),
            ];

            if ($category) {
                $query['media_category'] = $category;
            }

            if ($extraOwners) {
                $query['additional_owners'] = implode(',', $extraOwners);
            }

            $pool[++$i] = $this->request('POST', '/1.1/media/upload.json', [
                'query' => $query,
                'user_data' => [$i, null, 0, fopen($file->getPath(), 'r'), $alt, $subtitles],
            ]);

            if ($subtitles) {
                $query['total_bytes'] = $subtitles->getSize();
                $query['media_type'] = $subtitles->getContentType();
                $query['media_category'] = 'subtitles';

                $pool[++$i] = $this->request('POST', '/1.1/media/upload.json', [
                    'query' => $query,
                    'user_data' => [$i, null, 0, fopen($subtitles->getPath(), 'r'), null, $subtitles],
                ]);
            }
        }

        $mediaIds = [];
        $subtitlesVideoIds = [];
        $subtitlesMediaIds = [];
        $response = null;

        try {
            while ($pool) {
                foreach ($this->client->stream($pool) as $response => $chunk) {
                    $this->processChunk($pool, $response, $chunk, $mediaIds, $subtitlesVideoIds, $subtitlesMediaIds);
                }
            }
        } catch (ExceptionInterface $e) {
            if (null !== $response) {
                throw new TransportException($e->getMessage(), $response, 0, $e);
            }
            throw new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            foreach ($pool as $response) {
                $response->cancel();
            }
        }

        foreach (array_filter($subtitlesVideoIds) as $videoId => $subtitles) {
            $name = pathinfo($subtitles->getFilename(), \PATHINFO_FILENAME);
            $subtitlesVideoIds[$videoId] = $this->request('POST', '/1.1/media/subtitles/create.json', [
                'json' => [
                    'media_id' => $videoId,
                    'media_category' => 'tweet_video',
                    'subtitle_info' => [
                        'subtitles' => [
                            [
                                'media_id' => array_search($subtitles, $subtitlesMediaIds, true),
                                'language_code' => pathinfo($name, \PATHINFO_EXTENSION),
                                'display_name' => pathinfo($name, \PATHINFO_FILENAME),
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return $mediaIds;
    }

    private function processChunk(array &$pool, ResponseInterface $response, ChunkInterface $chunk, array &$mediaIds, array &$subtitlesVideoIds, array &$subtitlesMediaIds): void
    {
        if ($chunk->isFirst()) {
            $response->getStatusCode(); // skip non-2xx status codes
        }

        if (!$chunk->isLast()) {
            return;
        }

        if (400 <= $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException($error['errors'][0]['message'] ?? ($error['request'].': '.$error['error']), $response, $error['errors'][0]['code'] ?? 0);
        }

        [$i, $mediaId, $seq, $h, $alt, $subtitles] = $response->getInfo('user_data');
        unset($pool[$i]);

        $method = 'POST';
        $options = [];
        $mediaId ??= $response->toArray()['media_id_string'];
        $pause = 0;

        if (0 <= $seq) {
            $options['query'] = [
                'command' => 'APPEND',
                'media_id' => $mediaId,
                'segment_index' => (string) $seq,
            ];
            $options['body'] = ['media' => new DataPart(fread($h, 1024 * 1024))];
            $seq = feof($h) ? -1 : 1 + $seq;
        } elseif (-1 === $seq) {
            $options['query'] = ['command' => 'FINALIZE', 'media_id' => $mediaId];
            $seq = -2;
        } elseif (-2 !== $seq) {
            return;
        } elseif ('succeeded' === $state = $response->toArray()['processing_info']['state'] ?? 'succeeded') {
            if ($alt) {
                $pool[$i] = $this->request('POST', '/1.1/media/metadata/create.json', [
                    'json' => [
                        'media_id' => $mediaId,
                        'alt_text' => ['text' => $alt],
                    ],
                    'user_data' => [$i, $mediaId, -3, null, null, null],
                ]);
            }
            if (null !== $alt) {
                $mediaIds[] = $mediaId;
                $subtitlesVideoIds[$mediaId] = $subtitles;
            } else {
                $subtitlesMediaIds[$mediaId] = $subtitles;
            }

            return;
        } elseif ('failed' === $state) {
            $error = $response->toArray()['processing_info']['error'];

            throw new TransportException($error['message'], $response, $error['code']);
        } else {
            $method = 'GET';
            $options['query'] = ['command' => 'STATUS', 'media_id' => $mediaId];
            $pause = $response->toArray()['processing_info']['check_after_secs'];
        }

        $pool[$i] = $this->request($method, '/1.1/media/upload.json', $options + [
            'user_data' => [$i, $mediaId, $seq, $h, $alt, $subtitles],
        ]);

        if ($pause) {
            ($pool[$i]->getInfo('pause_handler') ?? sleep(...))($pause);
        }
    }
}
