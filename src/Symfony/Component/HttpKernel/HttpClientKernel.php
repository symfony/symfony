<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Help opcache.preload discover always-needed symbols
class_exists(ResponseHeaderBag::class);

/**
 * An implementation of a Symfony HTTP kernel using a "real" HTTP client.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HttpClientKernel implements HttpKernelInterface
{
    // RFC 9651 bare items: decimal, integer, string, token, byte sequence, boolean, date and display string
    private const SF_BARE_ITEM = '-?\d{1,12}\.\d{1,3}|-?\d{1,15}|"(?:[\x20\x21\x23-\x5B\x5D-\x7E]|\\\\["\\\\])*"|[A-Za-z*][-!#$%&\'*+.^_`|~0-9A-Za-z:/]*|:[A-Za-z0-9+/=]*:|\?[01]|@-?\d{1,15}|%"(?:[\x20\x21\x23\x24\x26-\x7E]|%[0-9a-f]{2})*"';

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        if (null === $client && !class_exists(HttpClient::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
        }

        $this->client = $client ?? HttpClient::create();
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        $headers = $this->getHeaders($request);
        $body = '';
        if (null !== $part = $this->getBody($request)) {
            $headers = array_merge($headers, $part->getPreparedHeaders()->toArray());
            $body = $part->bodyToIterable();
        }
        $response = $this->client->request($request->getMethod(), $request->getUri(), [
            'headers' => $headers,
            'body' => $body,
        ] + $request->attributes->get('http_client_options', []) + [
            'buffer' => static fn (array $headers): bool => !self::isIncremental($headers['incremental'] ?? []),
        ]);

        $headers = new class($response->getHeaders(!$catch)) extends ResponseHeaderBag {
            protected function computeCacheControlValue(): string
            {
                return $this->getCacheControlHeader(); // preserve the original value
            }
        };
        $headers->remove('X-Body-File');
        $headers->remove('X-Body-Eval');
        $headers->remove('X-Content-Digest');

        if (self::isIncremental($headers->all('incremental'))) {
            return new StreamedResponse($this->streamContent($response), $response->getStatusCode(), $headers);
        }

        try {
            return new Response($response->getContent(!$catch), $response->getStatusCode(), $headers);
        } catch (\TypeError) {
            // BC with Symfony < 8.1
            $response = new Response($response->getContent(!$catch), $response->getStatusCode());
            $response->headers = $headers;

            return $response;
        }
    }

    private function getBody(Request $request): ?AbstractPart
    {
        if (\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return null;
        }

        if (!class_exists(AbstractPart::class)) {
            throw new \LogicException('You cannot pass non-empty bodies as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        if ($content = $request->getContent()) {
            return new TextPart($content, 'utf-8', 'plain', '8bit');
        }

        $fields = $request->request->all();
        foreach ($request->files->all() as $name => $file) {
            $fields[$name] = DataPart::fromPath($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType());
        }

        return new FormDataPart($fields);
    }

    private function getHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers as $key => $value) {
            $headers[$key] = $value;
        }
        $cookies = [];
        foreach ($request->cookies->all() as $name => $value) {
            $cookies[] = $name.'='.$value;
        }
        if ($cookies) {
            $headers['cookie'] = implode('; ', $cookies);
        }

        return $headers;
    }

    private static function isIncremental(array $values): bool
    {
        // RFC 10036: the field is a Boolean Item, true is "?1" with optional parameters, and any other value is ignored
        return 1 === \count($values) && preg_match('{^ *\?1(?:; *[a-z*][-a-z0-9_.*]*(?:=(?:'.self::SF_BARE_ITEM.'))?)* *$}D', $values[0]);
    }

    private function streamContent(ResponseInterface $response): \Generator
    {
        while (true) {
            foreach ($this->client->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    // stream() stops watching a response after yielding its timeout chunk
                    continue 2;
                }

                yield $chunk->getContent();
            }

            return;
        }
    }
}
