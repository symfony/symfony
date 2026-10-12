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

use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class LoginLinkAuthenticationTest extends AbstractWebTestCase
{
    use ClockSensitiveTrait;

    public function testLoginLinkSuccess()
    {
        $client = $this->createClient(['test_case' => 'LoginLink', 'root_config' => 'config.yml', 'debug' => true]);

        // we need an active request that is under the firewall to use the linker
        $request = Request::create('/get-login-link');
        self::getContainer()->get(RequestStack::class)->push($request);

        /** @var LoginLinkHandlerInterface $loginLinkHandler */
        $loginLinkHandler = self::getContainer()->get(LoginLinkHandlerInterface::class);
        $user = new InMemoryUser('weaverryan', 'foo');
        $loginLink = $loginLinkHandler->createLoginLink($user);
        $this->assertStringContainsString('user=weaverryan', $loginLink);
        $this->assertStringContainsString('hash=', $loginLink);
        $this->assertStringContainsString('expires=', $loginLink);
        $client->request('GET', $loginLink->getUrl());
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome weaverryan!'], json_decode($response->getContent(), true));

        $client->request('GET', $loginLink->getUrl());
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $client->request('GET', $loginLink->getUrl());
        $response = $client->getResponse();
        $this->assertSame(302, $response->getStatusCode(), 'Should redirect with an error because max uses are only 2');
    }

    public function testLoginLinkExpiresWithTheClock()
    {
        self::mockTime('2100-01-01 00:00:00 UTC');
        $client = $this->createClient(['test_case' => 'LoginLink', 'root_config' => 'config.yml']);

        self::getContainer()->get(RequestStack::class)->push(Request::create('/get-login-link'));
        $loginLink = self::getContainer()->get(LoginLinkHandlerInterface::class)->createLoginLink(new InMemoryUser('weaverryan', 'foo'));

        $client->request('GET', $loginLink->getUrl());
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        self::mockTime('+11 minutes');

        $client->request('GET', $loginLink->getUrl());
        $this->assertRedirect($client->getResponse(), '/login');
    }

    public function testUsedLoginLinksAreForgottenWhenTheKernelIsShutDown()
    {
        self::mockTime('2100-01-01 00:00:00 UTC');
        $client = $this->createClient(['test_case' => 'LoginLink', 'root_config' => 'config.yml']);
        $loginLinkUrl = $this->createLoginLinkUrl();

        for ($i = 0; $i < 2; ++$i) {
            $client->request('GET', $loginLinkUrl);
            $this->assertSame(200, $client->getResponse()->getStatusCode());
        }

        self::ensureKernelShutdown();

        $client = $this->createClient(['test_case' => 'LoginLink', 'root_config' => 'config.yml']);
        $this->assertSame($loginLinkUrl, $this->createLoginLinkUrl());

        $client->request('GET', $loginLinkUrl);
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    private function createLoginLinkUrl(): string
    {
        self::getContainer()->get(RequestStack::class)->push(Request::create('/get-login-link'));

        return self::getContainer()->get(LoginLinkHandlerInterface::class)->createLoginLink(new InMemoryUser('weaverryan', 'foo'))->getUrl();
    }
}
