<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symphony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symphony\Component\Security\Core\Role\Role;

class ExpressionVoterTest extends TestCase
{
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
        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        if ($tokenExpectsGetRoles) {
            $token->expects($this->once())
                ->method('getRoles')
                ->will($this->returnValue($roles));
        }

        return $token;
    }

    protected function createExpressionLanguage($expressionLanguageExpectsEvaluate = true)
    {
        $mock = $this->getMockBuilder('Symphony\Component\Security\Core\Authorization\ExpressionLanguage')->getMock();

        if ($expressionLanguageExpectsEvaluate) {
            $mock->expects($this->once())
                ->method('evaluate')
                ->will($this->returnValue(true));
        }

        return $mock;
    }

    protected function createTrustResolver()
    {
        return $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface')->getMock();
    }

    protected function createRoleHierarchy()
    {
        return $this->getMockBuilder('Symphony\Component\Security\Core\Role\RoleHierarchyInterface')->getMock();
    }

    protected function createExpression()
    {
        return $this->getMockBuilder('Symphony\Component\ExpressionLanguage\Expression')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
