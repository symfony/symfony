<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class RateLimiterTest extends AbstractWebTestCase
{
    public function testLimiterStateIsKeptAcrossTheRequestsOfATest()
    {
        $client = self::createClient(['test_case' => 'RateLimiter']);

        $this->consumeAllTokens($client, '/tickets');

        $client->request('GET', '/tickets');
        self::assertResponseStatusCodeSame(429);
    }

    public function testLimiterStateIsResetWhenTheKernelIsShutDown()
    {
        $client = self::createClient(['test_case' => 'RateLimiter']);
        $this->consumeAllTokens($client, '/tickets');

        self::ensureKernelShutdown();

        $client = self::createClient(['test_case' => 'RateLimiter']);
        $client->request('GET', '/tickets');
        self::assertResponseIsSuccessful();
    }

    public function testLimiterStateInAnotherCachePoolIsKept()
    {
        $client = self::createClient(['test_case' => 'RateLimiter']);
        $this->consumeAllTokens($client, '/own-pool');

        self::ensureKernelShutdown();

        $client = self::createClient(['test_case' => 'RateLimiter']);
        $client->request('GET', '/own-pool');
        self::assertResponseStatusCodeSame(429);
    }

    private function consumeAllTokens(KernelBrowser $client, string $uri): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', $uri);
            self::assertResponseIsSuccessful();
        }
    }
}
