<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Security\LdapAuthenticator;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LdapAuthenticatorTest extends TestCase
{
    public function testAuthenticate()
    {
        $decorated = $this->createMock(AuthenticatorInterface::class);
        $passport = new Passport(new UserBadge('test'), new PasswordCredentials('s3cret'));
        $decorated
            ->expects($this->once())
            ->method('authenticate')
            ->willReturn($passport)
        ;

        $authenticator = new LdapAuthenticator($decorated, 'serviceId');
        $request = new Request();

        $authenticator->authenticate($request);

        /** @var LdapBadge $badge */
        $badge = $passport->getBadge(LdapBadge::class);
        $this->assertNotNull($badge);
        $this->assertSame('serviceId', $badge->getLdapServiceId());
    }
}
