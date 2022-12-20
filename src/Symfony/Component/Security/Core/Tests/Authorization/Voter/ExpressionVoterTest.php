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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ExpressionVoterTest extends TestCase
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVoteWithTokenThatReturnsRoleNames($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        self::assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public function getVoteTests()
    {
        return [
            [[], [], VoterInterface::ACCESS_ABSTAIN, false, false],
            [[], ['FOO'], VoterInterface::ACCESS_ABSTAIN, false, false],

            [[], [$this->createExpression()], VoterInterface::ACCESS_DENIED, true, false],

            [['ROLE_FOO'], [$this->createExpression(), $this->createExpression()], VoterInterface::ACCESS_GRANTED],
            [['ROLE_BAR', 'ROLE_FOO'], [$this->createExpression()], VoterInterface::ACCESS_GRANTED],
        ];
    }

    protected function getTokenWithRoleNames(array $roles, $tokenExpectsGetRoles = true)
    {
        $token = self::createMock(AbstractToken::class);

        if ($tokenExpectsGetRoles) {
            $token->expects(self::once())
                ->method('getRoleNames')
                ->willReturn($roles);
        }

        return $token;
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        $mock = self::createMock(ExpressionLanguage::class);

        if ($expressionLanguageExpectsEvaluate) {
            $mock->expects(self::once())
                ->method('evaluate')
                ->willReturn(true);
        }

        return $mock;
    }

    protected function createTrustResolver()
    {
        return self::createMock(AuthenticationTrustResolverInterface::class);
    }

    protected function createAuthorizationChecker()
    {
        return self::createMock(AuthorizationCheckerInterface::class);
    }

    protected function createExpression()
    {
        return self::createMock(Expression::class);
    }
}
