<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ExpressionVoterTest extends TestCase
{
    #[DataProvider('getVoteTests')]
    public function testVoteWithTokenThatReturnsRoleNames($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public static function getVoteTests()
    {
        return [
            [[], [], VoterInterface::ACCESS_ABSTAIN, false, false],
            [[], ['FOO'], VoterInterface::ACCESS_ABSTAIN, false, false],

            [[], [self::createExpression()], VoterInterface::ACCESS_DENIED, true, false],

            [['ROLE_FOO'], [self::createExpression(), self::createExpression()], VoterInterface::ACCESS_GRANTED],
            [['ROLE_BAR', 'ROLE_FOO'], [self::createExpression()], VoterInterface::ACCESS_GRANTED],
        ];
    }

    public function testChecksDecideForTheVotedToken()
    {
        $tokenStorage = new TokenStorage();
        $trustResolver = new AuthenticationTrustResolver();
        $accessDecisionManager = new AccessDecisionManager([new RoleVoter(), new AuthenticatedVoter($trustResolver)]);
        $voter = new ExpressionVoter(new ExpressionLanguage(), $trustResolver, new AuthorizationChecker($tokenStorage, $accessDecisionManager), null, $accessDecisionManager);

        $admin = new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);
        $user = new UsernamePasswordToken(new InMemoryUser('user', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $rememberedUser = new RememberMeToken(new InMemoryUser('user', null, ['ROLE_USER']), 'main');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($admin, null, [new Expression('is_granted("ROLE_ADMIN")')]));

        $tokenStorage->setToken($admin);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($user, null, [new Expression('is_granted("ROLE_ADMIN")')]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($rememberedUser, null, [new Expression('is_fully_authenticated()')]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($rememberedUser, null, [new Expression('is_remember_me()')]));
    }

    public function testChecksOfTheLoggedInTokenGoThroughTheAuthorizationChecker()
    {
        $tokenStorage = new TokenStorage();
        $accessDecisionManager = new AccessDecisionManager([new RoleVoter()]);
        $authorizationChecker = new AuthorizationChecker($tokenStorage, $accessDecisionManager);
        $checks = [];
        $decoratedAuthorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $decoratedAuthorizationChecker->method('isGranted')->willReturnCallback(static function (...$args) use (&$checks, $authorizationChecker) {
            $checks[] = $args[0];

            return $authorizationChecker->isGranted(...$args);
        });
        $voter = new ExpressionVoter(new ExpressionLanguage(), new AuthenticationTrustResolver(), $decoratedAuthorizationChecker, null, $accessDecisionManager, $tokenStorage);

        $admin = new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);
        $user = new UsernamePasswordToken(new InMemoryUser('user', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $tokenWithoutUser = new class(['ROLE_ADMIN']) extends AbstractToken {};

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [new Expression('is_granted("ROLE_USER")')]));

        $tokenStorage->setToken($admin);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($admin, null, [new Expression('is_granted("ROLE_ADMIN")')]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($user, null, [new Expression('is_granted("ROLE_ADMIN")')]));

        $tokenStorage->setToken($tokenWithoutUser);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($tokenWithoutUser, null, [new Expression('is_granted("ROLE_ADMIN")')]));
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN'], $checks);
    }

    protected function getTokenWithRoleNames(array $roles, $tokenExpectsGetRoles = true)
    {
        if ($tokenExpectsGetRoles) {
            $mock = $this->createMock(AbstractToken::class);
            $mock->expects($this->once())
                ->method('getRoleNames')
                ->willReturn($roles);

            return $mock;
        }

        return new NullToken();
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        if ($expressionLanguageExpectsEvaluate) {
            $mock = $this->createMock(ExpressionLanguage::class);
            $mock->expects($this->once())
                ->method('evaluate')
                ->willReturn(true);

            return $mock;
        }

        return new ExpressionLanguage();
    }

    protected function createTrustResolver()
    {
        return $this->createStub(AuthenticationTrustResolverInterface::class);
    }

    protected function createAuthorizationChecker()
    {
        return $this->createStub(AuthorizationCheckerInterface::class);
    }

    protected static function createExpression()
    {
        return new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MANAGER")');
    }
}
