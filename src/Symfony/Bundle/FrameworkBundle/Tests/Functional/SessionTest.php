<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

/**
 * @group functional
 */
class SessionTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testWelcome($config)
    {
        $client = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        $client->insulate();

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
     * @dataProvider getConfigs
     */
    public function testFlash($config)
    {
        $client = $this->createClient(array('test_case' => 'Session', 'root_config' => $config));
        $client->insulate();

        // set flash
        $crawler = $client->request('GET', '/session_setflash/Hello%20world.');

        // check flash displays on redirect
        $this->assertContains('Hello world.', $client->followRedirect()->text());

        // check flash is gone
        $crawler = $client->request('GET', '/session_showflash');
        $this->assertContains('No flash was set.', $crawler->text());
    }

    public function getConfigs()
    {
        return array(
            array('config.yml'),
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
