<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bluesky;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\String\AbstractString;
use Symfony\Component\String\ByteString;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class BlueskyTransport extends AbstractTransport
{
    private array $authSession = [];

    public function __construct(
        #[\SensitiveParameter] private string $user,
        #[\SensitiveParameter] private string $password,
        private LoggerInterface $logger,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('bluesky://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if ([] === $this->authSession) {
            $this->authenticate();
        }

        $post = [
            '$type' => 'app.bsky.feed.post',
            'text' => $message->getSubject(),
            'createdAt' => (new \DateTimeImmutable())->format('Y-m-d\\TH:i:s.u\\Z'),
        ];
        if ([] !== $facets = $this->parseFacets($post['text'])) {
            $post['facets'] = $facets;
        }

        $response = $this->client->request('POST', sprintf('https://%s/xrpc/com.atproto.repo.createRecord', $this->getEndpoint()), [
            'auth_bearer' => $this->authSession['accessJwt'] ?? null,
            'json' => [
                'repo' => $this->authSession['did'] ?? null,
                'collection' => 'app.bsky.feed.post',
                'record' => $post,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote bluesky server.', $response, 0, $e);
        }

        if (200 === $statusCode) {
            $content = $response->toArray();
            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($content['cid']);

            return $sentMessage;
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Unexpected response from bluesky server.', $response, 0, $e);
        }

        $title = $content['error'] ?? '';
        $errorDescription = $content['message'] ?? '';

        throw new TransportException(sprintf('Unable to send message to Bluesky: Status code %d (%s) with message "%s".', $statusCode, $title, $errorDescription), $response);
    }

    private function authenticate(): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/xrpc/com.atproto.server.createSession', $this->getEndpoint()), [
            'json' => [
                'identifier' => $this->user,
                'password' => $this->password,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote bluesky server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Could not authenticate with the remote bluesky server.', $response);
        }

        try {
            $this->authSession = $response->toArray(false) ?? [];
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Unexpected response from bluesky server.', $response, 0, $e);
        }
    }

    private function parseFacets(string $input): array
    {
        $facets = [];
        $text = new ByteString($input);

        // regex based on: https://bluesky.com/specs/handle#handle-identifier-syntax
        $regex = '#[$|\W](@([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)#';
        foreach ($this->getMatchAndPosition($text, $regex) as $match) {
            $response = $this->client->request('GET', sprintf('https://%s/xrpc/com.atproto.identity.resolveHandle', $this->getEndpoint()), [
                'query' => [
                    'handle' => ltrim($match['match'], '@'),
                ],
            ]);
            try {
                if (200 !== $response->getStatusCode()) {
                    continue;
                }
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Could not reach the remote bluesky server. Tried to lookup username.', ['exception' => $e]);
                throw $e;
            }

            $did = $response->toArray(false)['did'] ?? null;
            if (null === $did) {
                $this->logger->error('Could not get a good response from bluesky server. Tried to lookup username.');
                continue;
            }

            $facets[] = [
                'index' => [
                    'byteStart' => $match['start'],
                    'byteEnd' => $match['end'],
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#mention',
                        'did' => $did,
                    ],
                ],
            ];
        }

        // partial/naive URL regex based on: https://stackoverflow.com/a/3809435
        // tweaked to disallow some trailing punctuation
        $regex = ';[$|\W](https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*[-a-zA-Z0-9@%_\+~#//=])?);';
        foreach ($this->getMatchAndPosition($text, $regex) as $match) {
            $facets[] = [
                'index' => [
                    'byteStart' => $match['start'],
                    'byteEnd' => $match['end'],
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => $match['match'],
                    ],
                ],
            ];
        }

        return $facets;
    }

    private function getMatchAndPosition(AbstractString $text, string $regex): array
    {
        $output = [];
        $handled = [];
        $matches = $text->match($regex, \PREG_PATTERN_ORDER);
        if ([] === $matches) {
            return $output;
        }

        $length = $text->length();
        foreach ($matches[1] as $match) {
            if (isset($handled[$match])) {
                continue;
            }
            $handled[$match] = true;
            $end = -1;
            while (null !== $start = $text->indexOf($match, min($length, $end + 1))) {
                $output[] = [
                    'start' => $start,
                    'end' => $end = $start + (new ByteString($match))->length(),
                    'match' => $match,
                ];
            }
        }

        return $output;
    }
}
