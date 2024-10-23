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
        
        // Check if the specified HttpClient exists
        if (!\array_key_exists($httpClientId, $httpClientDataCollector->getClients())) {
            static::fail(sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        $expectedRequestHasBeenFound = false;

        // Iterate over each request made by the client
        foreach ($httpClientDataCollector->getClients()[$httpClientId]['traces'] as $trace) {
            // Check URL and method
            if (($expectedUrl !== $trace['info']['url'] && $expectedUrl !== $trace['url'])
                || $expectedMethod !== $trace['method']) {
                continue;
            }

            // Check body if expected
            if ($expectedBody !== null) {
                $actualBody = null;

                // Handle different body formats (string, json)
                if (isset($trace['options']['body']) && !isset($trace['options']['json'])) {
                    $actualBody = \is_string($trace['options']['body']) ? $trace['options']['body'] : $trace['options']['body']->getValue(true);
                } elseif (isset($trace['options']['json'])) {
                    $actualBody = json_encode($trace['options']['json']->getValue(true));  // normalize JSON to string
                }

                // If the body doesn't match, skip this request
                if ($expectedBody !== $actualBody) {
                    continue;
                }
            }

            // Check headers if expected
            if ($expectedHeaders) {
                $actualHeaders = $trace['options']['headers'] ?? [];
                $headersMatch = true;

                foreach ($expectedHeaders as $key => $expectedHeaderValue) {
                    if (!isset($actualHeaders[$key]) || $actualHeaders[$key]->getValue(true) !== $expectedHeaderValue) {
                        $headersMatch = false;
                        break;
                    }
                }

                // If headers don't match, skip this request
                if (!$headersMatch) {
                    continue;
                }
            }

            // All conditions passed, request has been found
            $expectedRequestHasBeenFound = true;
            break;
        }

        // Assert that the expected request was found
        self::assertTrue($expectedRequestHasBeenFound, 'The expected request has not been called: "' . $expectedMethod . '" - "' . $expectedUrl . '"');
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
            static::fail(\sprintf('HttpClient "%s" is not registered.', $httpClientId));
        }

        foreach ($httpClientDataCollector->getClients()[$httpClientId]['traces'] as $trace) {
            if (($unexpectedUrl === $trace['info']['url'] || $unexpectedUrl === $trace['url'])
                && $expectedMethod === $trace['method']
            ) {
                $unexpectedUrlHasBeenFound = true;
                break;
            }
        }

        self::assertFalse($unexpectedUrlHasBeenFound, \sprintf('Unexpected URL called: "%s" - "%s"', $expectedMethod, $unexpectedUrl));
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
