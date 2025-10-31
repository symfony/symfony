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

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
    #[Group('transient')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    public function testProxy()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->expectException(TransportException::class);

        $client->request('GET', 'http://localhost:8888', ['proxy' => 'http://proxy.host:8000']);
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 5]);
    }
}
