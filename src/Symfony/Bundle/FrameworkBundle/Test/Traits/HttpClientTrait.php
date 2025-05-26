<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;

trait HttpClientTrait
{
    public static function getHttpClientDataCollector(): HttpClientDataCollector
    {
        /** @var KernelBrowser */
        $client = static::getClient();

        if (!$client instanceof KernelBrowser) {
            static::fail('"getClient()" must be an instance of "Symfony\Bundle\FrameworkBundle\KernelBrowser"');
        }

        if (!($profile = $client->getProfile())) {
            static::fail('The Profiler must be enabled for the current request. Please ensure to call "$client->enableProfiler()" before making the r
equest.');
        }

        $collector = $profile->getCollector('http_client');
        if (!$collector instanceof HttpClientDataCollector) {
            static::fail('The "http_client" collector must be an instance of "Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector".');
        }

        return $collector;
    }
}
