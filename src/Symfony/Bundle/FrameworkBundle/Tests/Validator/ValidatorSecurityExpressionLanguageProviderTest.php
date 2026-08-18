<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Validator;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Validator\ValidatorSecurityExpressionLanguageProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

class ValidatorSecurityExpressionLanguageProviderTest extends TestCase
{
    public function testIsGrantedReturnsTrue()
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN', null)
            ->willReturn(true);

        $el = $this->createExpressionLanguage($authorizationChecker);

        $this->assertTrue($el->evaluate('is_granted("ROLE_ADMIN")'));
    }

    public function testIsGrantedWithSubject()
    {
        $subject = new \stdClass();

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $subject)
            ->willReturn(true);

        $el = $this->createExpressionLanguage($authorizationChecker);

        $this->assertTrue($el->evaluate('is_granted("EDIT", subject)', ['subject' => $subject]));
    }

    public function testIsAuthenticated()
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('IS_AUTHENTICATED')
            ->willReturn(true);

        $el = $this->createExpressionLanguage($authorizationChecker);

        $this->assertTrue($el->evaluate('is_authenticated()'));
    }

    public function testIsFullyAuthenticated()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($this->createStub(TokenInterface::class));

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('IS_AUTHENTICATED_FULLY')
            ->willReturn(true);

        $el = $this->createExpressionLanguage($authorizationChecker, $tokenStorage);

        $this->assertTrue($el->evaluate('is_fully_authenticated()'));
    }

    public function testIsFullyAuthenticatedReturnsFalseWhenNoToken()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->never())->method('isGranted');

        $el = $this->createExpressionLanguage($authorizationChecker, $tokenStorage);

        $this->assertFalse($el->evaluate('is_fully_authenticated()'));
    }

    public function testIsRememberMe()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($this->createStub(TokenInterface::class));

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('IS_REMEMBERED')
            ->willReturn(true);

        $el = $this->createExpressionLanguage($authorizationChecker, $tokenStorage);

        $this->assertTrue($el->evaluate('is_remember_me()'));
    }

    private function createExpressionLanguage(AuthorizationCheckerInterface $authorizationChecker, ?TokenStorageInterface $tokenStorage = null): ExpressionLanguage
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ValidatorSecurityExpressionLanguageProvider($authorizationChecker, $tokenStorage ?? $this->createStub(TokenStorageInterface::class), $this->createRequestStack()));

        return $expressionLanguage;
    }

    private function createRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return $requestStack;
    }

    public function testTheFunctionsReturnFalseWithoutAnyToken()
    {
        $tokenStorage = new TokenStorage();
        $accessDecisionManager = new AccessDecisionManager([new AuthenticatedVoter(new AuthenticationTrustResolver()), new RoleVoter()]);

        $el = new ExpressionLanguage();
        $el->registerProvider(new ValidatorSecurityExpressionLanguageProvider(new AuthorizationChecker($tokenStorage, $accessDecisionManager), $tokenStorage, $this->createRequestStack()));

        $this->assertFalse($el->evaluate('is_granted("ROLE_ADMIN")'));
        $this->assertFalse($el->evaluate('is_authenticated()'));
        $this->assertFalse($el->evaluate('is_fully_authenticated()'));
        $this->assertFalse($el->evaluate('is_remember_me()'));
    }

    #[TestWith(['is_granted("ROLE_ADMIN")'])]
    #[TestWith(['is_authenticated()'])]
    #[TestWith(['is_fully_authenticated()'])]
    #[TestWith(['is_remember_me()'])]
    public function testTheFunctionsCannotBeEvaluatedOutsideOfARequest(string $expression)
    {
        $el = new ExpressionLanguage();
        $el->registerProvider(new ValidatorSecurityExpressionLanguageProvider($this->createStub(AuthorizationCheckerInterface::class), new TokenStorage(), new RequestStack()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be evaluated outside of a request');
        $el->evaluate($expression);
    }

    #[TestWith(['is_granted("ROLE_ADMIN")'])]
    #[TestWith(['is_authenticated()'])]
    #[TestWith(['is_fully_authenticated()'])]
    #[TestWith(['is_remember_me()'])]
    public function testTheFunctionsCannotBeCompiled(string $expression)
    {
        $el = new ExpressionLanguage();
        $el->registerProvider(new ValidatorSecurityExpressionLanguageProvider($this->createStub(AuthorizationCheckerInterface::class), new TokenStorage(), new RequestStack()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('can only be evaluated');
        $el->compile($expression);
    }
}
