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
    public function __construct(mixed $body = [], array $info = [])
    {
        try {
            $json = json_encode($body, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('JSON encoding failed: '.$e->getMessage(), $e->getCode(), $e);
        }

        $info['response_headers']['content-type'] ??= 'application/json';

        parent::__construct($json, $info);
    }
}
