<?php

/*
 *  This file is part of the Symfony package.
 *
 *  (c) Fabien Potencier <fabien@symfony.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Turns a ResponseInterface to a string and vice-versa. Generated string should be modifiable easily.
 *
 * @author Gary PEGEOT <gary.pegeot@allopneus.com>
 */
class ResponseSerializer
{
    private const SEPARATOR = \PHP_EOL.\PHP_EOL;

    public function serialize(ResponseInterface $response): string
    {
        $parts = [
            $response->getStatusCode(),
            $this->serializeHeaders($response->getHeaders(false)),
            $response->getContent(false),
        ];

        return implode(static::SEPARATOR, $parts);
    }

    public function deserialize(string $content): array
    {
        [$statusCode, $unparsedHeaders, $body] = explode(static::SEPARATOR, $content, 3);
        $headers = [];

        foreach (explode(\PHP_EOL, $unparsedHeaders) as $row) {
            [$name, $values] = explode(':', $row, 2);
            $name = strtolower(trim($name));

            if ('set-cookie' === $name) {
                $headers[$name][] = trim($values);
            } else {
                $headers[$name] = array_map('trim', explode(',', $values));
            }
        }

        return [(int) $statusCode, $headers, $body];
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function serializeHeaders(array $headers): string
    {
        $parts = [];
        foreach ($headers as $name => $values) {
            $name = strtolower(trim($name));

            if ('set-cookie' === strtolower($name)) {
                foreach ($values as $value) {
                    $parts[] = "{$name}: {$value}";
                }
            } else {
                $parts[] = sprintf('%s: %s', $name, implode(', ', $values));
            }
        }

        return implode(\PHP_EOL, $parts);
    }
}
