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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
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

    #[DataProvider('provideReAuthenticationRequests')]
    public function testADeniedExpressionRequestsTheReAuthenticationOfTheCheckItFailedOn(array $roles, string $expression, ?string $requestedAttribute)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password', $roles), 'main', $roles);
        $voter = new ExpressionVoter(new ExpressionLanguage(), new AuthenticationTrustResolver(300), $this->createNestedAuthorizationChecker($token));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, [new Expression($expression)], $vote = new Vote()));
        $this->assertSame($requestedAttribute, $vote->reAuthenticationAttribute);
    }

    public static function provideReAuthenticationRequests(): iterable
    {
        yield 'role and recency, stale admin' => [['ROLE_ADMIN'], "is_granted('ROLE_ADMIN') and is_recently_authenticated()", AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY];
        yield 'role and recency, not an admin' => [['ROLE_USER'], "is_granted('ROLE_ADMIN') and is_recently_authenticated()", null];
        yield 'role or recency' => [['ROLE_USER'], "is_granted('ROLE_ADMIN') or is_granted('IS_AUTHENTICATED_VERY_RECENTLY')", AuthenticatedVoter::IS_AUTHENTICATED_VERY_RECENTLY];
        yield 'role only' => [['ROLE_USER'], "is_granted('ROLE_ADMIN')", null];
        yield 'voter checking recency before denying' => [['ROLE_USER'], "is_granted('CAN_DELETE')", null];
    }

    public function testADeniedExpressionKeepsTheReasonsOfItsChecks()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);
        $voter = new ExpressionVoter(new ExpressionLanguage(), new AuthenticationTrustResolver(300), $this->createNestedAuthorizationChecker($token));

        $voter->vote($token, null, [new Expression("is_granted('ROLE_ADMIN') and is_recently_authenticated()")], $vote = new Vote());

        $this->assertSame(['The user is not authenticated recently enough.', "Expression (is_granted('ROLE_ADMIN') and is_recently_authenticated()) is false."], $vote->reasons);
    }

    public function testAnExpressionCanStillCheckAnotherUser()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']);
        $voter = new ExpressionVoter(new ExpressionLanguage(), new AuthenticationTrustResolver(300), $this->createNestedAuthorizationChecker($token));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, new InMemoryUser('jane', 'password', ['ROLE_ADMIN']), [new Expression("auth_checker.isGrantedForUser(subject, 'ROLE_ADMIN')")]));
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

    private function createNestedAuthorizationChecker(TokenInterface $token): AuthorizationChecker
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $voters = new \ArrayObject([new AuthenticatedVoter(new AuthenticationTrustResolver(300)), new RoleVoter()]);
        $authorizationChecker = new AuthorizationChecker($tokenStorage, new AccessDecisionManager($voters));
        $voters[] = new RecencyCheckingOwnerVoter($authorizationChecker);

        return $authorizationChecker;
    }
}

final class RecencyCheckingOwnerVoter extends Voter
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'CAN_DELETE' === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->authorizationChecker->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY) && $subject?->owner === $token->getUserIdentifier();
    }
}
