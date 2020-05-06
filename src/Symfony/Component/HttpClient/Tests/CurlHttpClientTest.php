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

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @requires extension curl
 */
class CurlHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        if (false !== strpos($testCase, 'Push')) {
            if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
                $this->markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
            }

            if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073d00 > ($v = curl_version())['version_number'] || !(CURL_VERSION_HTTP2 & $v['features'])) {
                $this->markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
            }
        }

        return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
    }
}
