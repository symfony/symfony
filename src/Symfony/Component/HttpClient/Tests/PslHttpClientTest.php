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
use Symfony\Component\HttpClient\PslHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

#[Group('dns-sensitive')]
class PslHttpClientTest extends HttpClientTestCase
{
    #[Group('transient')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new PslHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 30]);
    }

    public function testMaxConnectDurationInfo()
    {
        BaseHttpClientTestCase::testMaxConnectDurationInfo();
    }

    public function testMaxConnectDuration()
    {
        BaseHttpClientTestCase::testMaxConnectDuration();
    }
}
