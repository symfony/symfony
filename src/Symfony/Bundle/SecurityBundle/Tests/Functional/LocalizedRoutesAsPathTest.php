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

    /**
     * @dataProvider getLocales
     */
    public function testAccessRestrictedResource($locale)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'localized_routes.yml'));
        $client->insulate();

        $client->request('GET', '/'.$locale.'/secure/');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/login');
    }

    /**
     * @dataProvider getLocales
     */
    public function testAccessRestrictedResourceWithForward($locale)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'localized_routes_with_forward.yml'));
        $client->insulate();

        $crawler = $client->request('GET', '/'.$locale.'/secure/');
        $this->assertEquals(1, count($crawler->selectButton('login')), (string) $client->getResponse());
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
        parent::tearDown();

        $this->deleteTmpDir('StandardFormLogin');
    }
}
