<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Har;

use Composer\InstalledVersions;
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\Recorder\Matcher\MatcherInterface;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see https://w3c.github.io/web-performance/specs/HAR/Overview.html
 *
 * @psalm-type HarEntry = array{
 *     startedDateTime: string,
 *     time: int,
 *     request: array{
 *         method: string,
 *         url: string,
 *         httpVersion: string,
 *         cookies: list<array>,
 *         headers: list<array{name: string, value: string}>,
 *         queryString: list<array>,
 *         postData: ?array{mimeType: string, text: string, encoding?: string},
 *         headersSize: int,
 *         bodySize: int,
 *     },
 *     response: array{
 *         status: int,
 *         statusText: string,
 *         httpVersion: string,
 *         cookies: list<array>,
 *         headers: list<array{name: string, value: string}>,
 *         content: array{size: int, mimeType: string, text: string, encoding?: string},
 *         redirectURL: string,
 *         headersSize: int,
 *         bodySize: int,
 *     },
 *     cache: array,
 *     timings: array{send: int, wait: int, receive: int},
 * }
 * @psalm-type HarLog = array{
 *     version: string,
 *     creator: array{name: string, version: string},
 *     entries: list<HarEntry>,
 * }
 * @psalm-type HarData = array{log: HarLog}
 */
final class HarFile
{
    /**
     * @psalm-param HarData $har
     */
    public function __construct(private array $har)
    {
    }

    public static function create(): self
    {
        return new self([
            'log' => [
                'version' => '1.2',
                'creator' => ['name' => 'symfony/http-client', 'version' => self::creatorVersion()],
                'entries' => [],
            ],
        ]);
    }

    /**
     * @throws \InvalidArgumentException when the file does not exist
     * @throws \JsonException            when the file does not contain valid JSON
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Invalid file path provided: "%s".', $path));
        }

        /** @psalm-var HarData $har */
        $har = json_decode(file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

        return new self($har);
    }

    /**
     * @param array<int> $consumed indexes already replayed, so that several recordings of the same request are served in order
     *
     * @throws HarEntryNotFoundException when no entry matches
     */
    public function findResponse(MatcherInterface $matcher, string $method, string $url, array $options = [], array &$consumed = []): ResponseInterface
    {
        if (null === $index = $this->findFirstUnconsumedEntryIndex($matcher, $method, $url, $options, $consumed)) {
            throw new HarEntryNotFoundException(\sprintf('No HAR entry found for method "%s" and URL "%s".', $method, $url));
        }

        $consumed[] = $index;
        $entry = $this->har['log']['entries'][$index];

        $info = [
            'http_code' => $entry['response']['status'],
            'http_method' => $entry['request']['method'],
            'response_headers' => [],
            'start_time' => strtotime($entry['startedDateTime']),
            'url' => $entry['request']['url'],
        ];

        foreach ($entry['response']['headers'] as $header) {
            $info['response_headers'][$header['name']][] = $header['value'];
        }

        return new MockResponse(self::decodeContent($entry['response']['content']), $info);
    }

    /**
     * Appends an entry, in the order it was recorded.
     *
     * @param array<string, string[]> $requestHeaders
     * @param array<string, string[]> $responseHeaders
     */
    public function addEntry(string $method, string $url, ?string $requestBody, array $requestHeaders, int $status, array $responseHeaders, string $content): self
    {
        /** @psalm-var HarEntry $entry */
        $entry = [
            'startedDateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
            'time' => 0,
            'request' => [
                'method' => $method,
                'url' => $url,
                'httpVersion' => 'HTTP/1.1',
                'cookies' => [],
                'headers' => self::formatHeaders($requestHeaders),
                'queryString' => [],
                'postData' => null !== $requestBody ? self::encodeContent($requestBody) + ['mimeType' => $requestHeaders['content-type'][0] ?? ''] : null,
                'headersSize' => -1,
                'bodySize' => null !== $requestBody ? \strlen($requestBody) : 0,
            ],
            'response' => [
                'status' => $status,
                'statusText' => '',
                'httpVersion' => 'HTTP/1.1',
                'cookies' => [],
                'headers' => self::formatHeaders($responseHeaders),
                'content' => self::encodeContent($content) + [
                    'size' => \strlen($content),
                    'mimeType' => $responseHeaders['content-type'][0] ?? '',
                ],
                'redirectURL' => '',
                'headersSize' => -1,
                'bodySize' => \strlen($content),
            ],
            'cache' => [],
            'timings' => ['send' => 0, 'wait' => 0, 'receive' => 0],
        ];

        $this->har['log']['entries'][] = $entry;

        return $this;
    }

    /**
     * @psalm-return HarData
     */
    public function toArray(): array
    {
        return $this->har;
    }

    /**
     * @param array{text?: string, encoding?: string, mimeType?: string, size?: int} $content
     */
    public static function decodeContent(array $content): string
    {
        $text = $content['text'] ?? '';
        $encoding = $content['encoding'] ?? null;

        return match ($encoding) {
            'base64' => base64_decode($text),
            null => $text,
            default => throw new \InvalidArgumentException(\sprintf('Unsupported encoding "%s", currently only base64 is supported.', $encoding)),
        };
    }

    /**
     * @return array{text: string, encoding?: string}
     */
    private static function encodeContent(string $text): array
    {
        if (preg_match('//u', $text)) {
            return ['text' => $text];
        }

        return ['text' => base64_encode($text), 'encoding' => 'base64'];
    }

    /**
     * @param array<int> $consumed
     */
    private function findFirstUnconsumedEntryIndex(MatcherInterface $matcher, string $method, string $url, array $options, array $consumed): ?int
    {
        $matchingIndexes = [];

        foreach ($this->har['log']['entries'] as $index => $entry) {
            if ($matcher->matches($entry, $method, $url, $options)) {
                $matchingIndexes[] = $index;
            }
        }

        if ([] === $matchingIndexes) {
            return null;
        }

        // the first matching entry that was not replayed yet
        foreach ($matchingIndexes as $index) {
            if (!\in_array($index, $consumed, true)) {
                return $index;
            }
        }

        // everything was replayed already: reuse the last match, so a single-entry fixture replays any number of times
        return $matchingIndexes[\count($matchingIndexes) - 1];
    }

    /**
     * @param array<string, string[]> $headers
     *
     * @return list<array{name: string, value: string}>
     */
    private static function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                $formatted[] = ['name' => $name, 'value' => $value];
            }
        }

        return $formatted;
    }

    private static function creatorVersion(): string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled('symfony/http-client')) {
            return 'unknown';
        }

        $version = InstalledVersions::getPrettyVersion('symfony/http-client');

        if (null === $version && InstalledVersions::isInstalled('symfony/symfony')) {
            $version = InstalledVersions::getPrettyVersion('symfony/symfony');
        }

        return $version ?? 'unknown';
    }
}
