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
     * @dataProvider provideClientOptions
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenAnonymous(array $options)
    {
        $client = self::createClient($options);
        $client->request('GET', '/protected_resource');

        self::assertRedirect($client->getResponse(), '/login');
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testRoutingErrorIsExposedWhenNotProtected(array $options)
    {
        $client = self::createClient($options);
        $client->request('GET', '/unprotected_resource');

        self::assertEquals(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenLoggedInWithInsufficientRights(array $options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->request('GET', '/highly_protected_resource');

        self::assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testSecurityConfigurationForSingleIPAddress(array $options)
    {
        $allowedClient = self::createClient($options, ['REMOTE_ADDR' => '10.10.10.10']);

        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, ['REMOTE_ADDR' => '10.10.20.10']);

        $this->assertAllowed($allowedClient, '/secured-by-one-ip');
        $this->assertRestricted($barredClient, '/secured-by-one-ip');
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testSecurityConfigurationForMultipleIPAddresses(array $options)
    {
        $allowedClientA = self::createClient($options, ['REMOTE_ADDR' => '1.1.1.1']);

        self::ensureKernelShutdown();

        $allowedClientB = self::createClient($options, ['REMOTE_ADDR' => '2.2.2.2']);

        self::ensureKernelShutdown();

        $allowedClientC = self::createClient($options, ['REMOTE_ADDR' => '203.0.113.0']);

        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, ['REMOTE_ADDR' => '192.168.1.1']);

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
        $allowedClient = self::createClient($options, ['HTTP_USER_AGENT' => 'Firefox 1.0']);
        $this->assertAllowed($allowedClient, '/protected-via-expression');
        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, []);
        $this->assertRestricted($barredClient, '/protected-via-expression');
        self::ensureKernelShutdown();

        $allowedClient = self::createClient($options, []);

        $allowedClient->request('GET', '/protected-via-expression');
        $form = $allowedClient->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $allowedClient->submit($form);
        self::assertRedirect($allowedClient->getResponse(), '/protected-via-expression');
        $this->assertAllowed($allowedClient, '/protected-via-expression');
    }

    public function testInvalidIpsInAccessControl()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('The given value "256.357.458.559" in the "security.access_control" config option is not a valid IP address.');

        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'invalid_ip_access_control.yml']);
        $client->request('GET', '/unprotected_resource');
    }

    public function testPublicHomepage()
    {
        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']);
        $client->request('GET', '/en/');

        self::assertEquals(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('public'));
        self::assertSame(0, self::getContainer()->get('request_tracker_subscriber')->getLastRequest()->getSession()->getUsageIndex());
    }

    /**
     * @dataProvider provideLegacyClientOptions
     * @group legacy
     */
    public function testLegacyRoutingErrorIsNotExposedForProtectedResourceWhenAnonymous(array $options)
    {
        $client = self::createClient($options);
        $client->request('GET', '/protected_resource');

        self::assertRedirect($client->getResponse(), '/login');
    }

    /**
     * @dataProvider provideLegacyClientOptions
     * @group legacy
     */
    public function testLegacyRoutingErrorIsExposedWhenNotProtected(array $options)
    {
        $client = self::createClient($options);
        $client->request('GET', '/unprotected_resource');

        self::assertEquals(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
    }

    /**
     * @dataProvider provideLegacyClientOptions
     * @group legacy
     */
    public function testLegacyRoutingErrorIsNotExposedForProtectedResourceWhenLoggedInWithInsufficientRights(array $options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->request('GET', '/highly_protected_resource');

        self::assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testLegacySecurityConfigurationForSingleIPAddress(array $options)
    {
        $allowedClient = self::createClient($options, ['REMOTE_ADDR' => '10.10.10.10']);

        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, ['REMOTE_ADDR' => '10.10.20.10']);

        $this->assertAllowed($allowedClient, '/secured-by-one-ip');
        $this->assertRestricted($barredClient, '/secured-by-one-ip');
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testLegacySecurityConfigurationForMultipleIPAddresses(array $options)
    {
        $allowedClientA = self::createClient($options, ['REMOTE_ADDR' => '1.1.1.1']);

        self::ensureKernelShutdown();

        $allowedClientB = self::createClient($options, ['REMOTE_ADDR' => '2.2.2.2']);

        self::ensureKernelShutdown();

        $allowedClientC = self::createClient($options, ['REMOTE_ADDR' => '203.0.113.0']);

        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, ['REMOTE_ADDR' => '192.168.1.1']);

        $this->assertAllowed($allowedClientA, '/secured-by-two-ips');
        $this->assertAllowed($allowedClientB, '/secured-by-two-ips');

        $this->assertRestricted($allowedClientA, '/secured-by-one-real-ip');
        $this->assertRestricted($allowedClientA, '/secured-by-one-real-ipv6');
        $this->assertAllowed($allowedClientC, '/secured-by-one-real-ip-with-mask');

        $this->assertRestricted($barredClient, '/secured-by-two-ips');
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyConfigs
     */
    public function testLegacySecurityConfigurationForExpression(array $options)
    {
        $allowedClient = self::createClient($options, ['HTTP_USER_AGENT' => 'Firefox 1.0']);
        $this->assertAllowed($allowedClient, '/protected-via-expression');
        self::ensureKernelShutdown();

        $barredClient = self::createClient($options, []);
        $this->assertRestricted($barredClient, '/protected-via-expression');
        self::ensureKernelShutdown();

        $allowedClient = self::createClient($options, []);

        $allowedClient->request('GET', '/protected-via-expression');
        $form = $allowedClient->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $allowedClient->submit($form);
        self::assertRedirect($allowedClient->getResponse(), '/protected-via-expression');
        $this->assertAllowed($allowedClient, '/protected-via-expression');
    }

    /**
     * @group legacy
     */
    public function testLegacyInvalidIpsInAccessControl()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('The given value "256.357.458.559" in the "security.access_control" config option is not a valid IP address.');

        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'invalid_ip_access_control.yml', 'enable_authenticator_manager' => false]);
        $client->request('GET', '/unprotected_resource');
    }

    /**
     * @group legacy
     */
    public function testLegacyPublicHomepage()
    {
        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_config.yml']);
        $client->request('GET', '/en/');

        self::assertEquals(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('public'));
        self::assertSame(0, self::getContainer()->get('request_tracker_subscriber')->getLastRequest()->getSession()->getUsageIndex());
    }

    private function assertAllowed($client, $path)
    {
        $client->request('GET', $path);
        self::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    private function assertRestricted($client, $path)
    {
        $client->request('GET', $path);
        self::assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public function provideClientOptions()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml', 'enable_authenticator_manager' => true]];
    }

    public function provideLegacyClientOptions()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml', 'enable_authenticator_manager' => true]];
    }

    public function provideConfigs()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml']];
    }

    public function provideLegacyConfigs()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_config.yml']];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_routes_as_path.yml']];
    }
}
