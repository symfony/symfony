<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguageProvider;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ExpressionLanguageProviderTest extends TestCase
{
    public function testCurrentUserFallsBackToTheTokenStorage()
    {
        $user = new InMemoryUser('username', 'password');

        $this->assertSame($user, $this->createExpressionLanguage(tokenStorage: $this->createTokenStorage($user))->evaluate('current_user()'));
    }

    public function testCurrentUserIsNullWithoutAuthenticatedUser()
    {
        $this->assertNull($this->createExpressionLanguage(tokenStorage: new TokenStorage())->evaluate('current_user()'));
        $this->assertNull($this->createExpressionLanguage()->evaluate('current_user()'));
    }

    public function testCurrentUserPrefersTheTokenVariable()
    {
        $user = new InMemoryUser('username', 'password');
        $expressionLanguage = $this->createExpressionLanguage(tokenStorage: $this->createTokenStorage(new InMemoryUser('authenticated', 'password')));

        $this->assertSame($user, $expressionLanguage->evaluate('current_user()', [
            'token' => new UsernamePasswordToken($user, 'firewall-name'),
        ]));
    }

    /**
     * A null "token" variable means nobody is authenticated, as when isGrantedForUser() checks
     * guest permissions. Falling back to the token storage would answer for somebody else.
     */
    public function testCurrentUserDoesNotFallBackOnANullTokenVariable()
    {
        $expressionLanguage = $this->createExpressionLanguage(tokenStorage: $this->createTokenStorage(new InMemoryUser('username', 'password')));

        $this->assertNull($expressionLanguage->evaluate('current_user()', ['token' => null]));
    }

    public function testIsGrantedFallsBackToTheAuthorizationChecker()
    {
        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(true));

        $this->assertTrue($expressionLanguage->evaluate('is_granted("ROLE_ADMIN")'));
    }

    public function testIsGrantedPrefersTheAuthCheckerVariable()
    {
        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(true));

        $this->assertFalse($expressionLanguage->evaluate('is_granted("ROLE_ADMIN")', [
            'auth_checker' => $this->createAuthorizationChecker(false),
        ]));
    }

    public function testIsFullyAuthenticatedFallsBackToTheServices()
    {
        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(true), $this->createTokenStorage(new InMemoryUser('username', 'password')));

        $this->assertTrue($expressionLanguage->evaluate('is_fully_authenticated()'));
        $this->assertFalse($this->createExpressionLanguage($this->createAuthorizationChecker(true), new TokenStorage())->evaluate('is_fully_authenticated()'));
    }

    public function testEvaluatingWithoutAuthorizationCheckerThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "is_granted()" function cannot be evaluated without an "auth_checker" variable');

        $this->createExpressionLanguage(tokenStorage: new TokenStorage())->evaluate('is_granted("ROLE_ADMIN")');
    }

    public function testEvaluatingOutsideOfARequestThrows()
    {
        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(true), $this->createTokenStorage(new InMemoryUser('username', 'password')), new RequestStack());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "current_user()" function cannot be evaluated outside of a request.');

        $expressionLanguage->evaluate('current_user()');
    }

    public function testEvaluatingInsideARequest()
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $user = new InMemoryUser('username', 'password');

        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(true), $this->createTokenStorage($user), $requestStack);

        $this->assertSame($user, $expressionLanguage->evaluate('current_user()'));
        $this->assertTrue($expressionLanguage->evaluate('is_granted("ROLE_ADMIN")'));
    }

    public function testTheRequestStackIsIgnoredWhenTheVariablesAreProvided()
    {
        $user = new InMemoryUser('username', 'password');
        $expressionLanguage = $this->createExpressionLanguage($this->createAuthorizationChecker(false), new TokenStorage(), new RequestStack());

        $this->assertSame($user, $expressionLanguage->evaluate('current_user()', [
            'token' => new UsernamePasswordToken($user, 'firewall-name'),
        ]));
    }

    #[DataProvider('provideExpressions')]
    public function testExpressionsAreCompiledWithoutServices(string $expression, string $compiled)
    {
        $this->assertSame($compiled, $this->createExpressionLanguage()->compile($expression));
    }

    public static function provideExpressions(): iterable
    {
        yield ['current_user()', '$token?->getUser()'];
        yield ['is_authenticated()', '$auth_checker->isGranted("IS_AUTHENTICATED")'];
        yield ['is_granted("ROLE_ADMIN")', '$auth_checker->isGranted("ROLE_ADMIN", null)'];
        yield ['is_remember_me()', '$token && $auth_checker->isGranted("IS_REMEMBERED")'];
    }

    public function testCompilingWithServicesThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "current_user()" function cannot be compiled, it can only be evaluated.');

        $this->createExpressionLanguage(tokenStorage: new TokenStorage())->compile('current_user()');
    }

    private function createExpressionLanguage(?AuthorizationCheckerInterface $authorizationChecker = null, ?TokenStorageInterface $tokenStorage = null, ?RequestStack $requestStack = null): ExpressionLanguage
    {
        return new ExpressionLanguage(null, [new ExpressionLanguageProvider($authorizationChecker, $tokenStorage, $requestStack)]);
    }

    private function createTokenStorage(InMemoryUser $user): TokenStorage
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'firewall-name'));

        return $tokenStorage;
    }

    private function createAuthorizationChecker(bool $isGranted): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($isGranted);

        return $authorizationChecker;
    }
}
