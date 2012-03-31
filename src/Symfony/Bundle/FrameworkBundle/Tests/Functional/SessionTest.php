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

/**
 * @group functional
 */
class SessionTest extends WebTestCase
{
    /**
     * Tests session attributes persist.
     *
     * @dataProvider getConfigs
     */
    public function testWelcome($config, $insulate)
    {
        $client = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        if ($insulate) {
            $client->insulate();
        }

        // no session
        $crawler = $client->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler->text());

        // remember name
        $crawler = $client->request('GET', '/session/drak');
        $this->assertContains('Hello drak, nice to meet you.', $crawler->text());

        // prove remembered name
        $crawler = $client->request('GET', '/session');
        $this->assertContains('Welcome back drak, nice to meet you.', $crawler->text());

        // clear session
        $crawler = $client->request('GET', '/session_logout');
        $this->assertContains('Session cleared.', $crawler->text());

        // prove cleared session
        $crawler = $client->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler->text());
    }

    /**
     * Tests flash messages work in practice.
     *
     * @dataProvider getConfigs
     */
    public function testFlash($config, $insulate)
    {
        $client = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        if ($insulate) {
            $client->insulate();
        }

        // set flash
        $crawler = $client->request('GET', '/session_setflash/Hello%20world.');

        // check flash displays on redirect
        $this->assertContains('Hello world.', $client->followRedirect()->text());

        // check flash is gone
        $crawler = $client->request('GET', '/session_showflash');
        $this->assertContains('No flash was set.', $crawler->text());
    }

    /**
     * See if two separate insulated clients can run without
     * polluting eachother's session data.
     *
     * @dataProvider getConfigs
     */
    public function testTwoClients($config, $insulate)
    {
        // start first client
        $client1 = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        if ($insulate) {
            $client1->insulate();
        }

        // start second client
        $client2 = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        if ($insulate) {
            $client2->insulate();
        }

        // new session, so no name set.
        $crawler1 = $client1->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler1->text());

        // set name of client1
        $crawler1 = $client1->request('GET', '/session/client1');
        $this->assertContains('Hello client1, nice to meet you.', $crawler1->text());

        // no session for client2
        $crawler2 = $client2->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler2->text());

        // remember name client2
        $crawler2 = $client2->request('GET', '/session/client2');
        $this->assertContains('Hello client2, nice to meet you.', $crawler2->text());

        // prove remembered name of client1
        $crawler1 = $client1->request('GET', '/session');
        $this->assertContains('Welcome back client1, nice to meet you.', $crawler1->text());

        // prove remembered name of client2
        $crawler2 = $client2->request('GET', '/session');
        $this->assertContains('Welcome back client2, nice to meet you.', $crawler2->text());

        // clear client1
        $crawler1 = $client1->request('GET', '/session_logout');
        $this->assertContains('Session cleared.', $crawler1->text());

        // prove client1 data is cleared
        $crawler1 = $client1->request('GET', '/session');
        $this->assertContains('You are new here and gave no name.', $crawler1->text());

        // prove remembered name of client2 remains untouched.
        $crawler2 = $client2->request('GET', '/session');
        $this->assertContains('Welcome back client2, nice to meet you.', $crawler2->text());
    }

    public function getConfigs()
    {
        return array(
            // configfile, insulate
            array('config.yml', true),
            array('config.yml', false),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('SessionTest');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('SessionTest');
    }
}
