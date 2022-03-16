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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientTest extends TestCase
{
    public function testCreateClient()
    {
        $this->assertInstanceOf(HttpClientInterface::class, HttpClient::create());
        $this->assertNotInstanceOf(NativeHttpClient::class, HttpClient::create());
    }
}
