<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;

/*
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */

trait HttpClientAssertionsTrait
{
    public static function assertHttpClientRequest(string $expectedUrl, string $expectedMethod = 'GET', string|array|null $expectedBody = null, array $expectedHeaders = [], string $httpClientId = 'http_client'): void
    {
        /** @var KernelBrowser $client */
        $client = static::getClient();

        if (!($profile = $client->getProfile())) {
            static::fail('The Profiler must be enabled for the current request. Please ensure to call "$client->enableProfiler()" before making the request.');
        }

        /** @var HttpClientDataCollector $httpClientDataCollector */
        $httpClientDataCollector = $profile->getCollector('http_client');
        $expectedRequestHasBeenFound = false;

        if (!\array_key_exists($httpClientId, $httpClientDataCollector->getClients())) {
            static::fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        foreach ($httpClientDataCollector->getClients()[$httpClientId]['traces'] as $trace) {
            if (($expectedUrl !== $trace['info']['url'] && $expectedUrl !== $trace['url'])
                || $expectedMethod !== $trace['method']
            ) {
                continue;
            }

            if (null !== $expectedBody) {
                $actualBody = null;

                if (null !== $trace['options']['body'] && null === $trace['options']['json']) {
                    $actualBody = \is_string($trace['options']['body']) ? $trace['options']['body'] : $trace['options']['body']->getValue(true);
                }

                if (null === $trace['options']['body'] && null !== $trace['options']['json']) {
                    $actualBody = $trace['options']['json']->getValue(true);
                }

                if (!$actualBody) {
                    continue;
                }

                if ($expectedBody === $actualBody) {
                    $expectedRequestHasBeenFound = true;

                    if (!$expectedHeaders) {
                        break;
                    }
                }
            }

            if ($expectedHeaders) {
                $actualHeaders = $trace['options']['headers'] ?? [];

                foreach ($actualHeaders as $headerKey => $actualHeader) {
                    if (\array_key_exists($headerKey, $expectedHeaders)
                        && $expectedHeaders[$headerKey] === $actualHeader->getValue(true)
                    ) {
                        $expectedRequestHasBeenFound = true;
                        break 2;
                    }
                }
            }

            $expectedRequestHasBeenFound = true;
            break;
        }

        self::assertTrue($expectedRequestHasBeenFound, 'The expected request has not been called: "'.$expectedMethod.'" - "'.$expectedUrl.'"');
    }

    public function assertNotHttpClientRequest(string $unexpectedUrl, string $expectedMethod = 'GET', string $httpClientId = 'http_client'): void
    {
        /** @var KernelBrowser $client */
        $client = static::getClient();

        if (!$profile = $client->getProfile()) {
            static::fail('The Profiler must be enabled for the current request. Please ensure to call "$client->enableProfiler()" before making the request.');
        }

        /** @var HttpClientDataCollector $httpClientDataCollector */
        $httpClientDataCollector = $profile->getCollector('http_client');
        $unexpectedUrlHasBeenFound = false;

        if (!\array_key_exists($httpClientId, $httpClientDataCollector->getClients())) {
            static::fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        foreach ($httpClientDataCollector->getClients()[$httpClientId]['traces'] as $trace) {
            if (($unexpectedUrl === $trace['info']['url'] || $unexpectedUrl === $trace['url'])
                && $expectedMethod === $trace['method']
            ) {
                $unexpectedUrlHasBeenFound = true;
                break;
            }
        }

        self::assertFalse($unexpectedUrlHasBeenFound, sprintf('Unexpected URL called: "%s" - "%s"', $expectedMethod, $unexpectedUrl));
    }

    public static function assertHttpClientRequestCount(int $count, string $httpClientId = 'http_client'): void
    {
        /** @var KernelBrowser $client */
        $client = static::getClient();

        if (!($profile = $client->getProfile())) {
            static::fail('The Profiler must be enabled for the current request. Please ensure to call "$client->enableProfiler()" before making the request.');
        }

        /** @var HttpClientDataCollector $httpClientDataCollector */
        $httpClientDataCollector = $profile->getCollector('http_client');

        self::assertCount($count, $httpClientDataCollector->getClients()[$httpClientId]['traces']);
    }
}
