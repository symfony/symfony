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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class LoginLinkAuthenticationTest extends AbstractWebTestCase
{
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
}
