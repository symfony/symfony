<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class AuthenticationCommencingTest extends WebTestCase
{
    public function testAuthenticationIsCommencingIfAccessDeniedExceptionIsWrapped()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'config.yml'));
        $client->insulate();

        $client->request('GET', '/secure-but-not-covered-by-access-control');
        $this->assertRedirect($client->getResponse(), '/login');
    }
}
