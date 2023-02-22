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

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\SecuredPageBundle\Security\Core\User\ArrayUserProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityTest extends AbstractWebTestCase
{
    public function testServiceIsFunctional()
    {
        $kernel = self::createKernel(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml']);
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new InMemoryUser('foo', 'pass');
        $token = new UsernamePasswordToken($user, 'provider', ['ROLE_USER']);
        $container->get('functional.test.security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        $this->assertTrue($security->isGranted('ROLE_USER'));
        $this->assertSame($token, $security->getToken());
        $request = new Request();
        $request->server->set('REQUEST_URI', '/main/foo');
        $this->assertInstanceOf(FirewallConfig::class, $firewallConfig = $security->getFirewallConfig($request));
        $this->assertSame('main', $firewallConfig->getName());
    }

    /**
     * @dataProvider userWillBeMarkedAsChangedIfRolesHasChangedProvider
     */
    public function testUserWillBeMarkedAsChangedIfRolesHasChanged(UserInterface $userWithAdminRole, UserInterface $userWithoutAdminRole)
    {
        $client = $this->createClient(['test_case' => 'AbstractTokenCompareRoles', 'root_config' => 'config.yml']);
        $client->disableReboot();

        /** @var ArrayUserProvider $userProvider */
        $userProvider = static::$kernel->getContainer()->get('security.user.provider.array');
        $userProvider->addUser($userWithAdminRole);

        $client->request('POST', '/login', [
            '_username' => 'user1',
            '_password' => 'test',
        ]);

        // user1 has ROLE_ADMIN and can visit secure page
        $client->request('GET', '/admin');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // updating user provider with same user but revoked ROLE_ADMIN from user1
        $userProvider->setUser('user1', $userWithoutAdminRole);

        // user1 has lost ROLE_ADMIN and MUST be redirected away from secure page
        $client->request('GET', '/admin');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    public static function userWillBeMarkedAsChangedIfRolesHasChangedProvider()
    {
        return [
            [
                new InMemoryUser('user1', 'test', ['ROLE_ADMIN']),
                new InMemoryUser('user1', 'test', ['ROLE_USER']),
            ],
            [
                new UserWithoutEquatable('user1', 'test', ['ROLE_ADMIN']),
                new UserWithoutEquatable('user1', 'test', ['ROLE_USER']),
            ],
        ];
    }

    /**
     * @testWith    ["form_login"]
     *              ["Symfony\\Bundle\\SecurityBundle\\Tests\\Functional\\Bundle\\AuthenticatorBundle\\ApiAuthenticator"]
     */
    public function testLogin(string $authenticator)
    {
        $client = $this->createClient(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml', 'debug' > true]);
        static::getContainer()->get(ForceLoginController::class)->authenticator = $authenticator;
        $client->request('GET', '/main/force-login');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @chalasr!'], json_decode($response->getContent(), true));
        $this->assertSame('chalasr', static::getContainer()->get('security.helper')->getUser()->getUserIdentifier());
    }

    public function testLogout()
    {
        $client = $this->createClient(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml', 'debug' => true]);
        $client->loginUser(new InMemoryUser('chalasr', 'the-password', ['ROLE_FOO']), 'main');

        $client->request('GET', '/main/force-logout');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(static::getContainer()->get('security.helper')->getUser());
        $this->assertSame(['message' => 'Logout successful'], json_decode($response->getContent(), true));
    }

    public function testLogoutWithCsrf()
    {
        $client = $this->createClient(['test_case' => 'SecurityHelper', 'root_config' => 'config_logout_csrf.yml', 'debug' => true]);
        $client->loginUser(new InMemoryUser('chalasr', 'the-password', ['ROLE_FOO']), 'main');

        // put a csrf token in the storage
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $setCsrfToken = function (RequestEvent $event) {
            static::getContainer()->get('security.csrf.token_storage')->setToken('logout', 'bar');
            $event->setResponse(new Response(''));
        };
        $eventDispatcher->addListener(KernelEvents::REQUEST, $setCsrfToken);
        try {
            $client->request('GET', '/'.uniqid('', true));
        } finally {
            $eventDispatcher->removeListener(KernelEvents::REQUEST, $setCsrfToken);
        }

        static::getContainer()->get(LogoutController::class)->checkCsrf = true;
        $client->request('GET', '/main/force-logout', ['_csrf_token' => 'bar']);
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(static::getContainer()->get('security.helper')->getUser());
        $this->assertSame(['message' => 'Logout successful'], json_decode($response->getContent(), true));
    }

    public function testLogoutBypassCsrf()
    {
        $client = $this->createClient(['test_case' => 'SecurityHelper', 'root_config' => 'config_logout_csrf.yml']);
        $client->loginUser(new InMemoryUser('chalasr', 'the-password', ['ROLE_FOO']), 'main');

        $client->request('GET', '/main/force-logout');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(static::getContainer()->get('security.helper')->getUser());
        $this->assertSame(['message' => 'Logout successful'], json_decode($response->getContent(), true));
    }
}

final class UserWithoutEquatable implements UserInterface, PasswordAuthenticatedUserInterface
{
    private $username;
    private $password;
    private $enabled;
    private $accountNonExpired;
    private $credentialsNonExpired;
    private $accountNonLocked;
    private $roles;

    public function __construct(?string $username, ?string $password, array $roles = [], bool $enabled = true, bool $userNonExpired = true, bool $credentialsNonExpired = true, bool $userNonLocked = true)
    {
        if ('' === $username || null === $username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->username = $username;
        $this->password = $password;
        $this->enabled = $enabled;
        $this->accountNonExpired = $userNonExpired;
        $this->credentialsNonExpired = $credentialsNonExpired;
        $this->accountNonLocked = $userNonLocked;
        $this->roles = $roles;
    }

    public function __toString(): string
    {
        return $this->getUserIdentifier();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSalt(): string
    {
        return '';
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function isAccountNonExpired(): bool
    {
        return $this->accountNonExpired;
    }

    public function isAccountNonLocked(): bool
    {
        return $this->accountNonLocked;
    }

    public function isCredentialsNonExpired(): bool
    {
        return $this->credentialsNonExpired;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function eraseCredentials(): void
    {
    }
}

class ForceLoginController
{
    public $authenticator = 'form_login';

    public function __construct(private Security $security)
    {
    }

    public function welcome()
    {
        $user = new InMemoryUser('chalasr', 'the-password', ['ROLE_FOO']);
        $this->security->login($user, $this->authenticator);

        return new JsonResponse(['message' => sprintf('Welcome @%s!', $this->security->getUser()->getUserIdentifier())]);
    }
}

class LogoutController
{
    public $checkCsrf = false;

    public function __construct(private Security $security)
    {
    }

    public function logout(UserInterface $user)
    {
        $this->security->logout($this->checkCsrf);

        return new JsonResponse(['message' => 'Logout successful']);
    }
}

class LoggedInController
{
    public function __invoke(UserInterface $user)
    {
        return new JsonResponse(['message' => sprintf('Welcome back @%s', $user->getUserIdentifier())]);
    }
}
