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

    public function testWarmUpCompilesTheConditionOfRateLimit()
    {
        $kernel = self::bootKernel(['test_case' => 'RateLimiter']);

        self::getContainer()->get('expression_language.cache_warmer')->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertSame(['request.isMethod("POST")'], array_keys(require $kernel->getBuildDir().'/expression_language/controller.expression_language.php'));
    }

    public function testPolicy()
    {
        $client = self::createClient(['test_case' => 'RateLimiter']);

        $this->consumeAllTokens($client, '/inline');

        $client->request('GET', '/inline');
        self::assertResponseStatusCodeSame(429);
    }

    public function testPolicyAttributeOnArgument()
    {
        $client = self::createClient(['test_case' => 'RateLimiter']);

        $this->consumeAllTokens($client, '/argument');

        $client->request('GET', '/argument');
        self::assertResponseStatusCodeSame(429);
        $this->assertSame('slow down', $client->getResponse()->getContent());
    }

    private function consumeAllTokens(KernelBrowser $client, string $uri): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', $uri);
            self::assertResponseIsSuccessful();
        }
    }
}
