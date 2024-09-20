<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;

class JsonMockResponse extends MockResponse
{
    /**
     * @param mixed $body Any value that `json_encode()` can serialize
     */
    public function __construct(mixed $body = [], array $info = [])
    {
        try {
            $json = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('JSON encoding failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        $info['response_headers']['content-type'] ??= 'application/json';

        parent::__construct($json, $info);
    }

    public static function fromFile(string $path, array $info = []): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(\sprintf('File not found: "%s".', $path));
        }

        $json = file_get_contents($path);
        if (!json_validate($json)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" does not contain valid JSON.', $path));
        }

        return new static(json_decode($json, true, flags: \JSON_THROW_ON_ERROR), $info);
    }
}
