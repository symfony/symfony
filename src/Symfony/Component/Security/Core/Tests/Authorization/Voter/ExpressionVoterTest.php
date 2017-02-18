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
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\Role;

class ExpressionVoterTest extends TestCase
{
    public function testSupportsAttribute()
    {
        $expression = $this->createExpression();
        $expressionLanguage = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\ExpressionLanguage')->getMock();
        $voter = new ExpressionVoter($expressionLanguage, $this->createTrustResolver(), $this->createRoleHierarchy());

        $this->assertTrue($voter->supportsAttribute($expression));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testVote($roles, $attributes, $expected, $tokenExpectsGetRoles = true, $expressionLanguageExpectsEvaluate = true)
    {
        $voter = new ExpressionVoter($this->createExpressionLanguage($expressionLanguageExpectsEvaluate), $this->createTrustResolver());

        $this->assertSame($expected, $voter->vote($this->getToken($roles, $tokenExpectsGetRoles), null, $attributes));
    }

    public function getVoteTests()
    {
        return array(
            array(array(), array(), VoterInterface::ACCESS_ABSTAIN, false, false),
            array(array(), array('FOO'), VoterInterface::ACCESS_ABSTAIN, false, false),

            array(array(), array($this->createExpression()), VoterInterface::ACCESS_DENIED, true, false),

            array(array('ROLE_FOO'), array($this->createExpression(), $this->createExpression()), VoterInterface::ACCESS_GRANTED),
            array(array('ROLE_BAR', 'ROLE_FOO'), array($this->createExpression()), VoterInterface::ACCESS_GRANTED),
        );
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
                ->will($this->returnValue($roles));
        }

        return $token;
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        $mock = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\ExpressionLanguage')->getMock();

        if ($expressionLanguageExpectsEvaluate) {
            $mock->expects($this->once())
                ->method('evaluate')
                ->will($this->returnValue(true));
        }

        return $mock;
    }

    protected function createTrustResolver()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface')->getMock();
    }

    protected function createRoleHierarchy()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Role\RoleHierarchyInterface')->getMock();
    }

    protected function createExpression()
    {
        return $this->getMockBuilder('Symfony\Component\ExpressionLanguage\Expression')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
