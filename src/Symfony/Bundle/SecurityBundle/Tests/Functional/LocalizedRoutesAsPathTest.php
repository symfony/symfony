<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class LocalizedRoutesAsPathTest extends WebTestCase
{
    /**
     * @dataProvider getLocales
     */
    public function testLoginLogoutProcedure($locale)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'localized_routes.yml'));
        $client->insulate();

        $crawler = $client->request('GET', '/'.$locale.'/login');
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/'.$locale.'/profile');
        $this->assertEquals('Profile', $client->followRedirect()->text());

        $client->request('GET', '/'.$locale.'/logout');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/');
        $this->assertEquals('Homepage', $client->followRedirect()->text());
    }

    public function getLocales()
    {
        return array(array('en'), array('de'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('StandardFormLogin');
    }

    protected function tearDown()
    {
        parent::setUp();

        $this->deleteTmpDir('StandardFormLogin');
    }
}