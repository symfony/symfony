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

class SessionTest extends AbstractWebTestCase
{
    /**
     * Tests session attributes persist.
     *
     * @dataProvider getConfigs
     */
    public function testWelcome($config, $insulate)
    {
        $client = $this->createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // no session
        $crawler = $client->request('GET', '/session');
        $this->assertStringContainsString('You are new here and gave no name.', $crawler->text());

        // remember name
        $crawler = $client->request('GET', '/session/drak');
        $this->assertStringContainsString('Hello drak, nice to meet you.', $crawler->text());

        // prove remembered name
        $crawler = $client->request('GET', '/session');
        $this->assertStringContainsString('Welcome back drak, nice to meet you.', $crawler->text());

        // clear session
        $crawler = $client->request('GET', '/session_logout');
        $this->assertStringContainsString('Session cleared.', $crawler->text());

        // prove cleared session
        $crawler = $client->request('GET', '/session');
        $this->assertStringContainsString('You are new here and gave no name.', $crawler->text());
    }

    /**
     * Tests flash messages work in practice.
     *
     * @dataProvider getConfigs
     */
    public function testFlash($config, $insulate)
    {
        $client = $this->createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // set flash
        $client->request('GET', '/session_setflash/Hello%20world.');

        // check flash displays on redirect
        $this->assertStringContainsString('Hello world.', $client->followRedirect()->text());

        // check flash is gone
        $crawler = $client->request('GET', '/session_showflash');
        $this->assertStringContainsString('No flash was set.', $crawler->text());
    }

    /**
     * createClient() can be called more than once in the same test, without manually
     * shutting the kernel down in between, to get several independent clients
     * (https://github.com/symfony/symfony/issues/36439). Each client keeps its own
     * cookies/session while sharing the same booted kernel.
     */
    public function testCreatingASecondClientDoesNotRequireManuallyShuttingDownTheKernel()
    {
        $harry = $this->createClient(['test_case' => 'Session', 'root_config' => 'config.yml']);
        $sally = $this->createClient(['test_case' => 'Session', 'root_config' => 'config.yml']);

        // both clients share the same booted kernel
        $this->assertSame($harry->getKernel(), $sally->getKernel());
        // but each has its own cookie jar / session
        $this->assertNotSame($harry->getCookieJar(), $sally->getCookieJar());

        $harry->request('GET', '/session/harry');
        $this->assertStringContainsString('Hello harry, nice to meet you.', $harry->getResponse()->getContent());

        $sally->request('GET', '/session/sally');
        $this->assertStringContainsString('Hello sally, nice to meet you.', $sally->getResponse()->getContent());

        // sessions remain independent: re-requesting shows each client's own remembered name
        $crawlerHarry = $harry->request('GET', '/session');
        $this->assertStringContainsString('Welcome back harry, nice to meet you.', $crawlerHarry->text());

        $crawlerSally = $sally->request('GET', '/session');
        $this->assertStringContainsString('Welcome back sally, nice to meet you.', $crawlerSally->text());
    }

    /**
     * See if two separate insulated clients can run without
     * polluting each other's session data.
     *
     * @dataProvider getConfigs
     */
    public function testTwoClients($config, $insulate)
    {
        // start first client
        $client1 = $this->createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client1->insulate();
        }

        $this->ensureKernelShutdown();

        // start second client
        $client2 = $this->createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client2->insulate();
        }

        // new session, so no name set.
        $crawler1 = $client1->request('GET', '/session');
        $this->assertStringContainsString('You are new here and gave no name.', $crawler1->text());

        // set name of client1
        $crawler1 = $client1->request('GET', '/session/client1');
        $this->assertStringContainsString('Hello client1, nice to meet you.', $crawler1->text());

        // no session for client2
        $crawler2 = $client2->request('GET', '/session');
        $this->assertStringContainsString('You are new here and gave no name.', $crawler2->text());

        // remember name client2
        $crawler2 = $client2->request('GET', '/session/client2');
        $this->assertStringContainsString('Hello client2, nice to meet you.', $crawler2->text());

        // prove remembered name of client1
        $crawler1 = $client1->request('GET', '/session');
        $this->assertStringContainsString('Welcome back client1, nice to meet you.', $crawler1->text());

        // prove remembered name of client2
        $crawler2 = $client2->request('GET', '/session');
        $this->assertStringContainsString('Welcome back client2, nice to meet you.', $crawler2->text());

        // clear client1
        $crawler1 = $client1->request('GET', '/session_logout');
        $this->assertStringContainsString('Session cleared.', $crawler1->text());

        // prove client1 data is cleared
        $crawler1 = $client1->request('GET', '/session');
        $this->assertStringContainsString('You are new here and gave no name.', $crawler1->text());

        // prove remembered name of client2 remains untouched.
        $crawler2 = $client2->request('GET', '/session');
        $this->assertStringContainsString('Welcome back client2, nice to meet you.', $crawler2->text());
    }

    /**
     * @dataProvider getConfigs
     */
    public function testCorrectCacheControlHeadersForCacheableAction($config, $insulate)
    {
        $client = $this->createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/cacheable');

        $response = $client->getResponse();
        $this->assertSame('public, s-maxage=100', $response->headers->get('cache-control'));
    }

    public static function getConfigs()
    {
        return [
            // configfile, insulate
            ['config.yml', true],
            ['config.yml', false],
        ];
    }
}
