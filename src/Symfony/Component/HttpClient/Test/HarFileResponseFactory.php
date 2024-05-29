<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Test;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * See: https://w3c.github.io/web-performance/specs/HAR/Overview.html.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class HarFileResponseFactory
{
    public function __construct(private string $archiveFile)
    {
    }

    public function setArchiveFile(string $archiveFile): void
    {
        $this->archiveFile = $archiveFile;
    }

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        if (!is_file($this->archiveFile)) {
            throw new \InvalidArgumentException(sprintf('Invalid file path provided: "%s".', $this->archiveFile));
        }

        $json = json_decode(json: file_get_contents($this->archiveFile), associative: true, flags: \JSON_THROW_ON_ERROR);

        foreach ($json['log']['entries'] as $entry) {
            /**
             * @var array{status: int, headers: array, content: array}  $response
             * @var array{method: string, url: string, postData: array} $request
             */
            ['response' => $response, 'request' => $request, 'startedDateTime' => $startedDateTime] = $entry;

            $body = $this->getContent($response['content']);
            $entryMethod = $request['method'];
            $entryUrl = $request['url'];
            $requestBody = $options['body'] ?? null;

            if ($method !== $entryMethod || $url !== $entryUrl) {
                continue;
            }

            if (null !== $requestBody && $requestBody !== $this->getContent($request['postData'] ?? [])) {
                continue;
            }

            $info = [
                'http_code' => $response['status'],
                'http_method' => $entryMethod,
                'response_headers' => [],
                'start_time' => strtotime($startedDateTime),
                'url' => $entryUrl,
            ];

            /** @var array{name: string, value: string} $header */
            foreach ($response['headers'] as $header) {
                ['name' => $name, 'value' => $value] = $header;

                $info['response_headers'][$name][] = $value;
            }

            return new MockResponse($body, $info);
        }

        throw new TransportException(sprintf('File "%s" does not contain a response for HTTP request "%s" "%s".', $this->archiveFile, $method, $url));
    }

    /**
     * @param array{text: string, encoding: string} $content
     */
    private function getContent(array $content): string
    {
        $text = $content['text'] ?? '';
        $encoding = $content['encoding'] ?? null;

        return match ($encoding) {
            'base64' => base64_decode($text),
            null => $text,
            default => throw new \InvalidArgumentException(sprintf('Unsupported encoding "%s", currently only base64 is supported.', $encoding)),
        };
    }
}
