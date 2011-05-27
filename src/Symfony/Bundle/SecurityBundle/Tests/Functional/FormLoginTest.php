<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

/**
 * @group functional
 */
class FormLoginTest extends WebTestCase
{
    public function testFormLogin()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin'));

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/');
    }

    public function testFormLoginWithCustomTargetPath()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin'));

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $form['_target_path'] = '/foo';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/foo');
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->deleteTmpDir('StandardFormLogin');
    }
}