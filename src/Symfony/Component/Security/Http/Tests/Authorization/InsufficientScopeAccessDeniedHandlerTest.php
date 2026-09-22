<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\AccessToken\SenderConstraintInterface;
use Symfony\Component\Security\Http\Authorization\InsufficientScopeAccessDeniedHandler;

class InsufficientScopeAccessDeniedHandlerTest extends TestCase
{
    public function testMissingScope()
    {
        $response = (new InsufficientScopeAccessDeniedHandler('My API'))->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(profile:read)']));

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="profile:read"', $response->headers->get('WWW-Authenticate'));
    }

    public function testTheRealmIsOptional()
    {
        $response = (new InsufficientScopeAccessDeniedHandler())->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(profile:read)']));

        $this->assertSame('Bearer error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="profile:read"', $response->headers->get('WWW-Authenticate'));
    }

    public function testEveryRequiredScopeIsReportedOnce()
    {
        $response = (new InsufficientScopeAccessDeniedHandler())->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(openid profile:read)', 'ROLE_USER', 'OAUTH2_SCOPE(openid)']));

        $this->assertStringEndsWith('scope="openid profile:read"', $response->headers->get('WWW-Authenticate'));
    }

    public function testADenialNoScopeTookPartInIsLeftAlone()
    {
        $handler = new InsufficientScopeAccessDeniedHandler();

        $this->assertNull($handler->handle(Request::create('/test'), $this->createException(['ROLE_USER'])));
        $this->assertNull($handler->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE_profile:read'])));
        $this->assertNull($handler->handle(Request::create('/test'), $this->createException([])));
        $this->assertNull($handler->handle(Request::create('/test'), $this->createException([new \stdClass()])));
    }

    public function testTheReportedScopesAreTheOnesTheAttributesRequire()
    {
        $handler = new InsufficientScopeAccessDeniedHandler();

        $this->assertStringEndsWith('scope="openid profile:read"', $handler->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(openid profile:read)']))->headers->get('WWW-Authenticate'));
        $this->assertStringEndsWith('scope="profile:read openid"', $handler->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(profile:read openid)']))->headers->get('WWW-Authenticate'));
    }

    public function testTheChallengeNamesTheSchemeTheTokensOfTheFirewallArePresentedUnder()
    {
        $senderConstraint = $this->createStub(SenderConstraintInterface::class);
        $senderConstraint
            ->method('getChallenge')
            ->willReturn(['DPoP', ['algs' => 'ES256']]);

        $handler = new InsufficientScopeAccessDeniedHandler(null, null, null, $senderConstraint);

        $this->assertSame('DPoP error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="openid"', $handler->handle(Request::create('/test'), $this->createException(['OAUTH2_SCOPE(openid)']))->headers->get('WWW-Authenticate'));
    }

    private function createException(array $attributes): AccessDeniedException
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes($attributes);

        return $exception;
    }
}
