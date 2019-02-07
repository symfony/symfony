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

use Symfony\Component\HttpClient\ApiClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\ApiClientInterface;
use Symfony\Contracts\HttpClient\Test\ApiClientTestCase;

/**
 * @requires extension curl
 */
class CurlApiClientTest extends ApiClientTestCase
{
    protected function getApiClient(): ApiClientInterface
    {
        return new ApiClient(new CurlHttpClient());
    }
}
