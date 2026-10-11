<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Bundle\SecurityBundle\Security\TestBrowserAccessTokenAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;

class TestBrowserAccessTokenAuthenticatorTest extends TestCase
{
    public function testAuthenticatesTheUserOfTheTestClientOnAFirewallWithoutUserProvider()
    {
        $user = new InMemoryUser('dunglas', null, ['ROLE_USER']);
        $token = new TestBrowserToken($user->getRoles(), $user, 'api');
        $token->setAttributes(['foo' => 'bar']);
        $request = Request::create('/');
        $request->attributes->set('_security_test_tokens', ['api' => $token]);

        $authenticator = new TestBrowserAccessTokenAuthenticator(new AccessTokenAuthenticator($this->createStub(AccessTokenHandlerInterface::class), new HeaderAccessTokenExtractor()), 'api');

        $this->assertNull($authenticator->supports($request));

        $passport = $authenticator->authenticate($request);

        $this->assertSame($user, $passport->getUser());

        $authenticatedToken = $authenticator->createToken($passport, 'api');

        $this->assertSame($user, $authenticatedToken->getUser());
        $this->assertSame(['ROLE_USER'], $authenticatedToken->getRoleNames());
        $this->assertSame(['foo' => 'bar', AccessTokenAuthenticator::SCOPE_ATTRIBUTE => []], $authenticatedToken->getAttributes());
    }

    public function testIgnoresTheUserOfTheTestClientOnAnotherFirewall()
    {
        $user = new InMemoryUser('dunglas', null, ['ROLE_USER']);
        $request = Request::create('/');
        $request->attributes->set('_security_test_tokens', ['main' => new TestBrowserToken($user->getRoles(), $user, 'main')]);

        $authenticator = new TestBrowserAccessTokenAuthenticator(new AccessTokenAuthenticator($this->createStub(AccessTokenHandlerInterface::class), new HeaderAccessTokenExtractor()), 'api');

        $this->assertFalse($authenticator->supports($request));
    }
}
