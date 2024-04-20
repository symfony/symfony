<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Response extends Message implements ResponseInterface
{
    public function __construct(
        string $version = '1.1',
        array $headers = [],
        StreamInterface $body = new Stream(),
        private readonly int $statusCode = 200,
    ) {
        parent::__construct($version, $headers, $body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getReasonPhrase(): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
