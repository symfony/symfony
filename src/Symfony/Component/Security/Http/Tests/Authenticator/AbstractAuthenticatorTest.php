<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class AbstractAuthenticatorTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testCreateToken()
    {
        $authenticator = new ConcreteAuthenticator();
        $this->assertInstanceOf(
            PostAuthenticationToken::class,
            $authenticator->createToken(new SelfValidatingPassport(new UserBadge('dummy', function () { return new InMemoryUser('robin', 'hood'); })), 'dummy')
        );
    }

    /**
     * @group legacy
     */
    public function testLegacyCreateAuthenticatedToken()
    {
        $authenticator = new ConcreteAuthenticator();
        $this->expectDeprecation('Since symfony/security-http 5.4: Method "Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator::createAuthenticatedToken()" is deprecated, use "Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator::createToken()" instead.');
        $this->assertInstanceOf(
            PostAuthenticationToken::class,
            $authenticator->createAuthenticatedToken(new SelfValidatingPassport(new UserBadge('dummy', function () { return new InMemoryUser('robin', 'hood'); })), 'dummy')
        );
    }
}

class ConcreteAuthenticator extends AbstractAuthenticator
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return parent::createToken($passport, $firewallName);
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return parent::createAuthenticatedToken($passport, $firewallName);
    }

    public function supports(Request $request): ?bool
    {
        return null;
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(new UserBadge('dummy'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
