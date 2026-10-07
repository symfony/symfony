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
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
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
 *
 * @internal
 */
final class HarFile
{
    /**
     * @psalm-param HarData $har
     */
    public function __construct(
        private array $har,
        private ?string $path = null,
    ) {
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

        return new self($har, $path);
    }

    /**
     * Loads the file (or starts a new one), hands it to the callback and writes it back atomically,
     * under a lock that keeps concurrent writers from losing entries.
     *
     * @template T
     *
     * @param \Closure(self):T $mutate
     *
     * @return T
     */
    public static function update(string $path, \Closure $mutate): mixed
    {
        return self::lock($path, static function () use ($path, $mutate) {
            $har = is_file($path) ? self::fromFile($path) : self::create();
            $result = $mutate($har);
            $har->save($path);

            return $result;
        });
    }

    /**
     * Writes the entry recorded at the given position of a recording session, which rewrites the file.
     *
     * The first position moves the previous version of the file aside.
     * An entry that records the same exchange as the entry at the same position in that previous version is kept as it was, so that recording an unchanged API again leaves the file unchanged.
     *
     * @psalm-param HarEntry $entry
     *
     * @return int The index of the entry
     */
    public static function rewrite(string $path, int $position, array $entry): int
    {
        return self::lock($path, static function () use ($path, $position, $entry): int {
            $previousPath = self::tempPath($path, 'previous.har');

            if (0 === $position) {
                if (is_file($path)) {
                    rename($path, $previousPath);
                } elseif (is_file($previousPath)) {
                    unlink($previousPath);
                }
            }

            $previous = is_file($previousPath) ? self::fromFile($previousPath)->har['log']['entries'][$position] ?? null : null;
            $har = 0 !== $position && is_file($path) ? self::fromFile($path) : self::create();
            $index = $har->addEntry(null !== $previous && self::isSameExchange($previous, $entry) ? $previous : $entry);
            $har->save($path);

            return $index;
        });
    }

    /**
     * @param string|null $body           the request body to compare, or null when it cannot be compared, like a streamed one
     * @param int[]       $consumed       indexes already replayed or recorded, so that several recordings of the same request are served in order
     * @param bool        $reuseLastMatch whether the last match is served again once all matches are consumed, instead of being a miss
     *
     * @throws HarEntryNotFoundException when no entry matches
     */
    public function findEntryIndex(string $method, string $url, ?string $body = null, array $consumed = [], bool $reuseLastMatch = true): int
    {
        $lastMatch = null;

        foreach ($this->har['log']['entries'] as $index => $entry) {
            if ($method !== ($entry['request']['method'] ?? null) || $url !== ($entry['request']['url'] ?? null)) {
                continue;
            }

            if (null !== $body && $body !== self::decodeContent($entry['request']['postData'] ?? [])) {
                continue;
            }

            if (!\in_array($index, $consumed, true)) {
                return $index;
            }

            $lastMatch = $index;
        }

        if (null !== $lastMatch && $reuseLastMatch) {
            return $lastMatch;
        }

        throw new HarEntryNotFoundException(\sprintf('File "%s" does not contain a response for HTTP request "%s" "%s".', $this->path ?? 'unknown', $method, $url));
    }

    public function createResponse(int $index): ResponseInterface
    {
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
     * @param array<string, string[]> $requestHeaders
     * @param array<string, string[]> $responseHeaders
     *
     * @psalm-return HarEntry
     */
    public static function createEntry(string $method, string $url, ?string $requestBody, array $requestHeaders, int $status, array $responseHeaders, string $content): array
    {
        return [
            'startedDateTime' => (class_exists(Clock::class) ? Clock::get()->now() : new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
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
    }

    /**
     * Appends an entry, in the order it was recorded.
     *
     * @psalm-param HarEntry $entry
     *
     * @return int The index of the new entry
     */
    public function addEntry(array $entry): int
    {
        $this->har['log']['entries'][] = $entry;

        return \count($this->har['log']['entries']) - 1;
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
     * @template T
     *
     * @param \Closure():T $callback
     *
     * @return T
     */
    private static function lock(string $path, \Closure $callback): mixed
    {
        $dir = \dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $dir));
        }

        // the lock lives outside the fixture directory, so that it never ends up committed next to the records
        if (false === $lock = @fopen(self::tempPath($path, 'lock'), 'c')) {
            throw new \RuntimeException(\sprintf('Unable to open the lock file for "%s".', $path));
        }

        try {
            flock($lock, \LOCK_EX);

            return $callback();
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    private static function tempPath(string $path, string $extension): string
    {
        return sys_get_temp_dir().'/sf_har_'.hash('xxh128', $path).'.'.$extension;
    }

    /**
     * @psalm-param HarEntry $a
     * @psalm-param HarEntry $b
     */
    private static function isSameExchange(array $a, array $b): bool
    {
        return $a['request']['method'] === $b['request']['method']
            && $a['request']['url'] === $b['request']['url']
            && ($a['request']['postData'] ?? null) === ($b['request']['postData'] ?? null)
            && $a['response']['status'] === $b['response']['status']
            && $a['response']['content'] === $b['response']['content'];
    }

    private function save(string $path): void
    {
        if (false === $tmp = @tempnam(\dirname($path), basename($path).'.')) {
            throw new \RuntimeException(\sprintf('Unable to create a temporary file next to "%s".', $path));
        }

        try {
            if (false === @file_put_contents($tmp, json_encode($this->har, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException(\sprintf('Unable to write the "%s" file.', $path));
            }

            @chmod($tmp, 0o666 & ~umask());

            if (!@rename($tmp, $path)) {
                throw new \RuntimeException(\sprintf('Unable to write the "%s" file.', $path));
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
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
