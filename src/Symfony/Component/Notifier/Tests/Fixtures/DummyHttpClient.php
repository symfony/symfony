<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Fixtures;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class DummyHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
    }

    public function withOptions(array $options): static
    {
    }
}
