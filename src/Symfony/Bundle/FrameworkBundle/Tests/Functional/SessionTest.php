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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

class SessionTest extends AbstractWebTestCase
{
    /**
     * Tests session attributes persist.
     */
    #[DataProvider('getConfigs')]
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

        // prepare session programmatically
        $session = $client->getSession();
        $session->set('name', 'drak');
        $session->save();

        // ensure session can be saved multiple times without being reset
        $session = $client->getSession();
        $session->set('foo', 'bar');
        $session->save();

        // prove remembered name from programmatically prepared session
        $crawler = $client->request('GET', '/session');
        $this->assertStringContainsString('Welcome back drak, nice to meet you.', $crawler->text());
    }

    /**
     * Tests flash messages work in practice.
     */
    #[DataProvider('getConfigs')]
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
     * See if two separate insulated clients can run without
     * polluting each other's session data.
     */
    #[DataProvider('getConfigs')]
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

    #[DataProvider('getConfigs')]
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

    public function testChangesWithoutSetAreNotSavedWithIsolatedAttributes()
    {
        $client = $this->createClient(['test_case' => 'Session', 'root_config' => 'config_isolate_attributes.yml', 'debug' => true]);

        $object = new \stdClass();
        $object->foo = 'bar';
        $session = $client->getSession();
        $session->set('obj', $object);
        $object->foo = 'baz';
        $session->save();

        $session = $client->getSession();
        $this->assertSame('bar', $session->get('obj')->foo);
        $session->get('obj')->foo = 'baz';
        $this->assertSame('baz', $session->get('obj')->foo);
        $session->save();

        $this->assertSame('bar', $client->getSession()->get('obj')->foo);
        $this->assertNotSame($client->getSession()->getBag('attributes'), $client->getSession()->getBag('attributes'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testChangesWithoutSetAreReportedInDebugMode()
    {
        $client = $this->createClient(['test_case' => 'Session', 'root_config' => 'config.yml', 'debug' => true]);

        $object = new \stdClass();
        $object->foo = 'bar';
        $session = $client->getSession();
        $session->set('obj', $object);
        $session->save();

        $session = $client->getSession();
        $session->get('obj')->foo = 'baz';

        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Saving changes made to the value of session attribute "obj" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.');

        $session->save();

        $this->assertSame('baz', $client->getSession()->get('obj')->foo);
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
