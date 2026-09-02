<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Core\User\OidcUserProvider;

class OidcUserProviderTest extends TestCase
{
    public function testLoadUserFromTheClaims()
    {
        $provider = new OidcUserProvider();

        $user = $provider->loadUserByIdentifier('user-42', [
            'sub' => 'user-42',
            'email' => 'test@example.com',
            'preferred_username' => 'john',
            'department' => 'engineering',
        ]);

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertSame('user-42', $user->getUserIdentifier());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('john', $user->getPreferredUsername());
        $this->assertSame(['department' => 'engineering'], $user->getAdditionalClaims());
    }

    public function testLoadUserWithoutClaims()
    {
        $provider = new OidcUserProvider();

        // the user is built from the claims collected during authentication, so there is
        // nothing to load from an identifier alone (e.g. impersonation, or a firewall
        // pairing this provider with another authenticator)
        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('user-42');
    }

    public function testLoadUserWithNonStringSub()
    {
        $provider = new OidcUserProvider();

        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('user-42', ['sub' => ['nested']]);
    }

    #[DataProvider('providePrivilegedClaims')]
    public function testLoadUserDoesNotMapPrivilegedClaims(string $claim, mixed $value)
    {
        // the OIDC provider must not be able to grant roles or define the security
        // identity through the claims it returns (privilege escalation)
        $provider = new OidcUserProvider();

        $user = $provider->loadUserByIdentifier('user-42', ['sub' => 'user-42', $claim => $value]);

        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertSame('user-42', $user->getUserIdentifier());
        $this->assertSame([], $user->getAdditionalClaims());
    }

    public static function providePrivilegedClaims(): iterable
    {
        yield 'roles' => ['roles', ['ROLE_ADMIN']];
        yield 'camel-cased roles' => ['Roles', ['ROLE_ADMIN']];
        yield 'user identifier' => ['user_identifier', 'spoofed-identity'];
        yield 'camel-cased user identifier' => ['userIdentifier', 'spoofed-identity'];
    }

    public function testRefreshUserReturnsSameUser()
    {
        $provider = new OidcUserProvider();
        $user = new OidcUser(userIdentifier: 'user-42', sub: 'user-42');

        $this->assertSame($user, $provider->refreshUser($user));
    }

    public function testRefreshUnsupportedUser()
    {
        $provider = new OidcUserProvider();

        $this->expectException(UnsupportedUserException::class);

        $provider->refreshUser(new InMemoryUser('john', 'pass'));
    }

    public function testSupportsClass()
    {
        $provider = new OidcUserProvider();

        $this->assertTrue($provider->supportsClass(OidcUser::class));
        $this->assertFalse($provider->supportsClass(InMemoryUser::class));
    }
}
