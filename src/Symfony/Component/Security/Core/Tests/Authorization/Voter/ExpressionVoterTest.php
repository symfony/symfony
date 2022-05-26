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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ExpressionVoterTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @dataProvider getGetVoteTests
     */
    public function testGetVoteWithTokenThatReturnsRoleNames(array $roles, array $attributes, Vote $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->assertEquals($expected, $voter->getVote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public function getGetVoteTests()
    {
        return [
            [[], [], Vote::createAbstain(), false, false],
            [[], ['FOO'], Vote::createAbstain(), false, false],

            [[], [$this->createExpression()], Vote::createDenied(), true, false],

            [['ROLE_FOO'], [$this->createExpression(), $this->createExpression()], Vote::createGranted()],
            [['ROLE_BAR', 'ROLE_FOO'], [$this->createExpression()], Vote::createGranted()],
        ];
    }

    /**
     * @group legacy
     * @dataProvider getVoteTestsLegacy
     */
    public function testVoteWithTokenThatReturnsRoleNamesLegacy($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->expectDeprecation('Since symfony/security-core 6.2: Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.');
        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public static function getVoteTestsLegacy()
    {
        return [
            [[], [], VoterInterface::ACCESS_ABSTAIN, false, false],
            [[], ['FOO'], VoterInterface::ACCESS_ABSTAIN, false, false],

            [[], [self::createExpression()], VoterInterface::ACCESS_DENIED, true, false],

            [['ROLE_FOO'], [self::createExpression(), self::createExpression()], VoterInterface::ACCESS_GRANTED],
            [['ROLE_BAR', 'ROLE_FOO'], [self::createExpression()], VoterInterface::ACCESS_GRANTED],
        ];
    }

    protected function getTokenWithRoleNames(array $roles, $tokenExpectsGetRoles = true)
    {
        $token = $this->createMock(AbstractToken::class);

        if ($tokenExpectsGetRoles) {
            $token->expects($this->once())
                ->method('getRoleNames')
                ->willReturn($roles);
        }

        return $token;
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        $mock = $this->createMock(ExpressionLanguage::class);

        if ($expressionLanguageExpectsEvaluate) {
            $mock->expects($this->once())
                ->method('evaluate')
                ->willReturn(true);
        }

        return $mock;
    }

    protected function createTrustResolver()
    {
        return $this->createMock(AuthenticationTrustResolverInterface::class);
    }

    protected function createAuthorizationChecker()
    {
        return $this->createMock(AuthorizationCheckerInterface::class);
    }

    protected static function createExpression()
    {
        return new Expression('');
    }
}
