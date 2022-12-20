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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

class SessionTest extends AbstractWebTestCase
{
    use ExpectDeprecationTrait;

    /**
     * Tests session attributes persist.
     *
     * @dataProvider getConfigs
     */
    public function testWelcome($config, $insulate)
    {
        $client = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // no session
        $crawler = $client->request('GET', '/session');
        self::assertStringContainsString('You are new here and gave no name.', $crawler->text());

        // remember name
        $crawler = $client->request('GET', '/session/drak');
        self::assertStringContainsString('Hello drak, nice to meet you.', $crawler->text());

        // prove remembered name
        $crawler = $client->request('GET', '/session');
        self::assertStringContainsString('Welcome back drak, nice to meet you.', $crawler->text());

        // clear session
        $crawler = $client->request('GET', '/session_logout');
        self::assertStringContainsString('Session cleared.', $crawler->text());

        // prove cleared session
        $crawler = $client->request('GET', '/session');
        self::assertStringContainsString('You are new here and gave no name.', $crawler->text());
    }

    /**
     * Tests flash messages work in practice.
     *
     * @dataProvider getConfigs
     */
    public function testFlash($config, $insulate)
    {
        $client = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // set flash
        $client->request('GET', '/session_setflash/Hello%20world.');

        // check flash displays on redirect
        self::assertStringContainsString('Hello world.', $client->followRedirect()->text());

        // check flash is gone
        $crawler = $client->request('GET', '/session_showflash');
        self::assertStringContainsString('No flash was set.', $crawler->text());
    }

    /**
     * Tests flash messages work when flashbag service is injected to the constructor.
     *
     * @group legacy
     * @dataProvider getConfigs
     */
    public function testFlashOnInjectedFlashbag($config, $insulate)
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.1: The "session.flash_bag" service is deprecated, use "$session->getFlashBag()" instead.');

        $client = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // set flash
        $client->request('GET', '/injected_flashbag/session_setflash/Hello%20world.');

        // check flash displays on redirect
        self::assertStringContainsString('Hello world.', $client->followRedirect()->text());

        // check flash is gone
        $crawler = $client->request('GET', '/session_showflash');
        self::assertStringContainsString('No flash was set.', $crawler->text());
    }

    /**
     * @group legacy
     * @dataProvider getConfigs
     */
    public function testSessionServiceTriggerDeprecation($config, $insulate)
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.3: The "session" service and "SessionInterface" alias are deprecated, use "$requestStack->getSession()" instead.');

        $client = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        // trigger deprecation
        $crawler = $client->request('GET', '/deprecated_session/trigger');

        // check response
        self::assertStringContainsString('done', $crawler->text());
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
        $client1 = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client1->insulate();
        }

        self::ensureKernelShutdown();

        // start second client
        $client2 = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client2->insulate();
        }

        // new session, so no name set.
        $crawler1 = $client1->request('GET', '/session');
        self::assertStringContainsString('You are new here and gave no name.', $crawler1->text());

        // set name of client1
        $crawler1 = $client1->request('GET', '/session/client1');
        self::assertStringContainsString('Hello client1, nice to meet you.', $crawler1->text());

        // no session for client2
        $crawler2 = $client2->request('GET', '/session');
        self::assertStringContainsString('You are new here and gave no name.', $crawler2->text());

        // remember name client2
        $crawler2 = $client2->request('GET', '/session/client2');
        self::assertStringContainsString('Hello client2, nice to meet you.', $crawler2->text());

        // prove remembered name of client1
        $crawler1 = $client1->request('GET', '/session');
        self::assertStringContainsString('Welcome back client1, nice to meet you.', $crawler1->text());

        // prove remembered name of client2
        $crawler2 = $client2->request('GET', '/session');
        self::assertStringContainsString('Welcome back client2, nice to meet you.', $crawler2->text());

        // clear client1
        $crawler1 = $client1->request('GET', '/session_logout');
        self::assertStringContainsString('Session cleared.', $crawler1->text());

        // prove client1 data is cleared
        $crawler1 = $client1->request('GET', '/session');
        self::assertStringContainsString('You are new here and gave no name.', $crawler1->text());

        // prove remembered name of client2 remains untouched.
        $crawler2 = $client2->request('GET', '/session');
        self::assertStringContainsString('Welcome back client2, nice to meet you.', $crawler2->text());
    }

    /**
     * @dataProvider getConfigs
     */
    public function testCorrectCacheControlHeadersForCacheableAction($config, $insulate)
    {
        $client = self::createClient(['test_case' => 'Session', 'root_config' => $config]);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/cacheable');

        $response = $client->getResponse();
        self::assertSame('public, s-maxage=100', $response->headers->get('cache-control'));
    }

    public function getConfigs()
    {
        return [
            // configfile, insulate
            ['config.yml', true],
            ['config.yml', false],
        ];
    }
}
