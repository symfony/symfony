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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class ExpressionVoterTest extends TestCase
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVoteWithTokenThatReturnsRoleNames($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->assertEquals($expected, $voter->vote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public function getVoteTests()
    {
        return [
            [[], [], Vote::createAbstain(), false, false],
            [[], ['FOO'], Vote::createAbstain(), false, false],

            [[], [$this->createExpression()], Vote::createDenied(), true, false],

            [['ROLE_FOO'], [$this->createExpression(), $this->createExpression()], Vote::createGranted()],
            [['ROLE_BAR', 'ROLE_FOO'], [$this->createExpression()], Vote::createGranted()],
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

    protected function createExpression()
    {
        return $this->createMock(Expression::class);
    }
}
