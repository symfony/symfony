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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

class SwitchUserTest extends AbstractWebTestCase
{
    #[DataProvider('getTestParameters')]
    public function testSwitchUser($originalUser, $targetUser, $expectedUser, $expectedStatus)
    {
        $client = $this->createAuthenticatedClient($originalUser, ['root_config' => 'switchuser.yml']);

        $client->request('GET', '/profile?_switch_user='.$targetUser);

        $this->assertEquals($expectedStatus, $client->getResponse()->getStatusCode());
        $this->assertEquals($expectedUser, $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchedUserCanSwitchToOther()
    {
        $client = $this->createAuthenticatedClient('user_can_switch');

        $client->request('GET', '/profile?_switch_user=user_cannot_switch_1');
        $client->request('GET', '/profile?_switch_user=user_cannot_switch_2');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_2', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchedUserExit()
    {
        $client = $this->createAuthenticatedClient('user_can_switch');

        $client->request('GET', '/profile?_switch_user=user_cannot_switch_1');
        $client->request('GET', '/profile?_switch_user='.SwitchUserListener::EXIT_VALUE);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_can_switch', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchUserViaPostOnlyRouteRejectsGet()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_path.yml']);

        // the route is declared POST-only, so a GET is rejected by routing before
        // it can ever reach the switch-user listener
        $client->request('GET', '/switch-user?_switch_user=user_cannot_switch_1');

        $this->assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testSwitchUserViaPostOnlyRoute()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_path.yml']);

        $client->request('POST', '/switch-user', ['_switch_user' => 'user_cannot_switch_1']);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchUserViaPathWithoutCsrfTokenIsDenied()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf.yml']);

        $client->request('POST', '/switch-user', ['_switch_user' => 'user_cannot_switch_1']);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_can_switch', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchUserViaPathWithValidCsrfToken()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf.yml']);

        // render a form carrying a valid session-bound CSRF token, then submit it
        $crawler = $client->request('GET', '/impersonation-form');
        $form = $crawler->selectButton('switch')->form();
        $form['_switch_user'] = 'user_cannot_switch_1';
        $client->submit($form);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testExitImpersonationPathTargetsTheConfiguredPath()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_path.yml']);

        $client->request('POST', '/switch-user', ['_switch_user' => 'user_cannot_switch_1', '_target_path' => '/profile']);

        $this->assertSame('/switch-user?_switch_user=_exit&_target_path=%2Fprofile', $client->getProfile()->getCollector('security')->getImpersonationExitPath());
    }

    public function testExitImpersonationPathCarriesACsrfToken()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf.yml']);

        $crawler = $client->request('GET', '/impersonation-form');
        $form = $crawler->selectButton('switch')->form();
        $form['_switch_user'] = 'user_cannot_switch_1';
        $client->submit($form);

        $exitPath = $client->getProfile()->getCollector('security')->getImpersonationExitPath();
        $this->assertMatchesRegularExpression('#^/switch-user\?_switch_user=_exit&_csrf_token=.+&_target_path=%2F$#', $exitPath);

        // the route is declared POST-only, so the generated URL is submitted as a POST
        $client->request('POST', $exitPath);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_can_switch', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testImpersonationPathCarriesACsrfToken()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf.yml']);

        $client->request('GET', '/impersonation-link');
        $client->request('POST', trim($client->getResponse()->getContent()));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testImpersonationPathCarriesATokenOfTheCsrfTokenManagerOfTheFirewall()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf_custom_manager.yml']);

        $client->request('GET', '/impersonation-link');
        $client->request('POST', trim($client->getResponse()->getContent()));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testTheImpersonationFormHelperBuildsAFormTheListenerAccepts()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf_custom_manager.yml']);

        $crawler = $client->request('GET', '/impersonation-form-helper');
        $client->submit($crawler->selectButton('switch')->form());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testTheExitFormHelperBuildsAFormTheListenerAccepts()
    {
        $client = $this->createAuthenticatedClient('user_can_switch', ['root_config' => 'switchuser_csrf_custom_manager.yml']);

        $crawler = $client->request('GET', '/impersonation-form-helper');
        $crawler = $client->submit($crawler->selectButton('switch')->form());
        $client->submit($crawler->selectButton('exit')->form());

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_can_switch', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchUserStateless()
    {
        $client = $this->createClient(['test_case' => 'JsonLogin', 'root_config' => 'switchuser_stateless.yml']);
        $client->request('POST', '/chk', [], [], ['HTTP_X_SWITCH_USER' => 'dunglas', 'CONTENT_TYPE' => 'application/json'], '{"user": {"login": "user_can_switch", "password": "test"}}');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
        $this->assertSame('dunglas', $client->getProfile()->getCollector('security')->getUser());
    }

    public static function getTestParameters()
    {
        return [
            'unauthorized_user_cannot_switch' => ['user_cannot_switch_1', 'user_cannot_switch_1', 'user_cannot_switch_1', 403],
            'authorized_user_can_switch' => ['user_can_switch', 'user_cannot_switch_1', 'user_cannot_switch_1', 200],
            'authorized_user_cannot_switch_to_non_existent' => ['user_can_switch', 'user_does_not_exist', 'user_can_switch', 403],
            'authorized_user_can_switch_to_himself' => ['user_can_switch', 'user_can_switch', 'user_can_switch', 200],
        ];
    }

    protected function createAuthenticatedClient($username, array $options = [])
    {
        $client = $this->createClient($options + ['test_case' => 'StandardFormLogin', 'root_config' => 'switchuser.yml']);
        $client->followRedirects(true);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = $username;
        $form['_password'] = 'test';
        $client->submit($form);

        return $client;
    }
}
