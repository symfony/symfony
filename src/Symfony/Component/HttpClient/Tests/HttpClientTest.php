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
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;

class HttpClientTest extends TestCase
{
    public function testCreateClient()
    {
        if (\extension_loaded('curl') && ('\\' !== \DIRECTORY_SEPARATOR || ini_get('curl.cainfo') || ini_get('openssl.cafile') || ini_get('openssl.capath')) && 0x073d00 <= curl_version()['version_number']) {
            $this->assertInstanceOf(CurlHttpClient::class, HttpClient::create());
        } else {
            $this->assertInstanceOf(AmpHttpClient::class, HttpClient::create());
        }
    }
}
