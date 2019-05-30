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
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\Role;

class ExpressionVoterTest extends TestCase
{
    /**
     * @group legacy
     * @dataProvider getVoteTests
     */
    public function testVote($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->assertSame($expected, $voter->vote($this->getToken($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testVoteWithTokenThatReturnsRoleNames($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver(), $this->createAuthorizationChecker());

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles, $tokenExpectsGetRoles), null, $attributes));
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

    protected function getToken(array $roles, $tokenExpectsGetRoles = true)
    {
        foreach ($roles as $i => $role) {
            $roles[$i] = new Role($role);
        }
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        if ($tokenExpectsGetRoles) {
            $token->expects($this->once())
                ->method('getRoles')
                ->willReturn($roles);
        }

        return $token;
    }

    protected function getTokenWithRoleNames(array $roles, $tokenExpectsGetRoles = true)
    {
        $token = $this->getMockBuilder(AbstractToken::class)->getMock();

        if ($tokenExpectsGetRoles) {
            $token->expects($this->once())
                ->method('getRoleNames')
                ->willReturn($roles);
        }

        return $token;
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        $mock = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\ExpressionLanguage')->getMock();

        if ($expressionLanguageExpectsEvaluate) {
            $mock->expects($this->once())
                ->method('evaluate')
                ->willReturn(true);
        }

        return $mock;
    }

    protected function createTrustResolver()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface')->getMock();
    }

    protected function createAuthorizationChecker()
    {
        return $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
    }

    protected function createExpression()
    {
        return $this->getMockBuilder('Symfony\Component\ExpressionLanguage\Expression')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
