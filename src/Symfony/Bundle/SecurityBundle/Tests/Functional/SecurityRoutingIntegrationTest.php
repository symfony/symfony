<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

class SecurityRoutingIntegrationTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideConfigs
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenAnonymous(array $options)
    {
        $client = $this->createClient($options);
        $client->request('GET', '/protected_resource');

        $this->assertRedirect($client->getResponse(), '/login');
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testRoutingErrorIsExposedWhenNotProtected(array $options)
    {
        $client = $this->createClient($options);
        $client->request('GET', '/unprotected_resource');

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenLoggedInWithInsufficientRights(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->request('GET', '/highly_protected_resource');

        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testSecurityConfigurationForSingleIPAddress(array $options)
    {
        $allowedClient = $this->createClient($options, ['REMOTE_ADDR' => '10.10.10.10']);

        $this->ensureKernelShutdown();

        $barredClient = $this->createClient($options, ['REMOTE_ADDR' => '10.10.20.10']);

        $this->assertAllowed($allowedClient, '/secured-by-one-ip');
        $this->assertRestricted($barredClient, '/secured-by-one-ip');
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testSecurityConfigurationForMultipleIPAddresses(array $options)
    {
        $allowedClientA = $this->createClient($options, ['REMOTE_ADDR' => '1.1.1.1']);

        $this->ensureKernelShutdown();

        $allowedClientB = $this->createClient($options, ['REMOTE_ADDR' => '2.2.2.2']);

        $this->ensureKernelShutdown();

        $allowedClientC = $this->createClient($options, ['REMOTE_ADDR' => '203.0.113.0']);

        $this->ensureKernelShutdown();

        $barredClient = $this->createClient($options, ['REMOTE_ADDR' => '192.168.1.1']);

        $this->assertAllowed($allowedClientA, '/secured-by-two-ips');
        $this->assertAllowed($allowedClientB, '/secured-by-two-ips');

        $this->assertRestricted($allowedClientA, '/secured-by-one-real-ip');
        $this->assertRestricted($allowedClientA, '/secured-by-one-real-ipv6');
        $this->assertAllowed($allowedClientC, '/secured-by-one-real-ip-with-mask');

        $this->assertRestricted($barredClient, '/secured-by-two-ips');
    }

    /**
     * @dataProvider provideConfigs
     */
    public function testSecurityConfigurationForExpression(array $options)
    {
        $allowedClient = $this->createClient($options, ['HTTP_USER_AGENT' => 'Firefox 1.0']);
        $this->assertAllowed($allowedClient, '/protected-via-expression');
        $this->ensureKernelShutdown();

        $barredClient = $this->createClient($options, []);
        $this->assertRestricted($barredClient, '/protected-via-expression');
        $this->ensureKernelShutdown();

        $allowedClient = $this->createClient($options, []);

        $allowedClient->request('GET', '/protected-via-expression');
        $form = $allowedClient->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $allowedClient->submit($form);
        $this->assertRedirect($allowedClient->getResponse(), '/protected-via-expression');
        $this->assertAllowed($allowedClient, '/protected-via-expression');
    }

    public function testInvalidIpsInAccessControl()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The given value "256.357.458.559" in the "security.access_control" config option is not a valid IP address.');

        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'invalid_ip_access_control.yml']);
        $client->request('GET', '/unprotected_resource');
    }

    public function testPublicHomepage()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']);
        $client->request('GET', '/en/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
        $this->assertTrue($client->getResponse()->headers->getCacheControlDirective('public'));
        $this->assertSame(0, self::getContainer()->get('request_tracker_subscriber')->getLastRequest()->getSession()->getUsageIndex());
    }

    private function assertAllowed($client, $path)
    {
        $client->request('GET', $path);
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    private function assertRestricted($client, $path)
    {
        $client->request('GET', $path);
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public static function provideConfigs()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml']];
    }
}
