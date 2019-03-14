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
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase;

abstract class NativeConditionalHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(): HttpClientInterface
    {
        return new ConditionalHttpClient(new NativeHttpClient(), [
            '#^.*length-broken$#' => ['headers' => [
                'ConditionalHttpClient' => 'NativeHttpClient',
                'ConditionalHttpClient2' => 'url:length-broken',
            ]],
            '#^.*$#' => ['headers' => ['ConditionalHttpClient' => 'NativeHttpClient']],
        ]);
    }
}
