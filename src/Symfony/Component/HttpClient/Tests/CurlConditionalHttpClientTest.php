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

use Symfony\Component\HttpClient\ConditionalHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase;

/**
 * @requires extension curl
 */
class CurlConditionalHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(): HttpClientInterface
    {
        return new ConditionalHttpClient(new CurlHttpClient(), [
            '#^.*length-broken$#' => ['headers' => [
                'ConditionalHttpClient' => 'CurlHttpClient',
                'ConditionalHttpClient2' => 'url:length-broken',
            ]],
            '#^.*$#' => ['headers' => ['ConditionalHttpClient' => 'CurlHttpClient']],
        ]);
    }
}
