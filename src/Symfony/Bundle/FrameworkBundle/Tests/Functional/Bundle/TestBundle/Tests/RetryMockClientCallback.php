<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Tests;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fails every response but the last one, so the retry strategy keeps going.
 */
class RetryMockClientCallback
{
    private int $calls = 0;

    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        return new MockResponse('', ['http_code' => 2 > ++$this->calls ? 500 : 200]);
    }
}
