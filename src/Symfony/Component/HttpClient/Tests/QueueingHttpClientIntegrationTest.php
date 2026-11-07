<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\QueueingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase;

class QueueingHttpClientIntegrationTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new QueueingHttpClient(HttpClient::create(), 2);
    }

    public function testProxy()
    {
        if (HttpClient::create() instanceof AmpHttpClient) {
            $this->markTestSkipped('A real proxy server would be needed.');
        }

        parent::testProxy();
    }
}
