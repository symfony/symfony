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
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;

class HttpClientTest extends TestCase
{
    public function testCreateClient()
    {
        if (\extension_loaded('curl')) {
            $this->assertInstanceOf(CurlHttpClient::class, HttpClient::create());
        } else {
            $this->assertInstanceOf(NativeHttpClient::class, HttpClient::create());
        }
    }
}
