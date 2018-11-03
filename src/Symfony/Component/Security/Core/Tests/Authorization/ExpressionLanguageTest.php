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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\User\User;

class ExpressionLanguageTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testIsAuthenticated($token, $expression, $result)
    {
        $expressionLanguage = new ExpressionLanguage();
        $trustResolver = new AuthenticationTrustResolver();
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $accessDecisionManager = new AccessDecisionManager(array(new RoleVoter()));
        $authChecker = new AuthorizationChecker($tokenStorage, $this->getMockBuilder(AuthenticationManagerInterface::class)->getMock(), $accessDecisionManager);

        $context = array();
        $context['trust_resolver'] = $trustResolver;
        $context['auth_checker'] = $authChecker;
        $context['token'] = $token;

        $this->assertEquals($result, $expressionLanguage->evaluate($expression, $context));
    }

    public function provider()
    {
        $roles = array('ROLE_USER', 'ROLE_ADMIN');
        $user = new User('username', 'password', $roles);

        $noToken = null;
        $anonymousToken = new AnonymousToken('firewall', 'anon.');
        $rememberMeToken = new RememberMeToken($user, 'providerkey', 'firewall');
        $usernamePasswordToken = new UsernamePasswordToken('username', 'password', 'providerkey', $roles);

        return array(
            array($noToken, 'is_anonymous()', false),
            array($noToken, 'is_authenticated()', false),
            array($noToken, 'is_fully_authenticated()', false),
            array($noToken, 'is_remember_me()', false),

            array($anonymousToken, 'is_anonymous()', true),
            array($anonymousToken, 'is_authenticated()', false),
            array($anonymousToken, 'is_fully_authenticated()', false),
            array($anonymousToken, 'is_remember_me()', false),
            array($anonymousToken, "is_granted('ROLE_USER')", false),

            array($rememberMeToken, 'is_anonymous()', false),
            array($rememberMeToken, 'is_authenticated()', true),
            array($rememberMeToken, 'is_fully_authenticated()', false),
            array($rememberMeToken, 'is_remember_me()', true),
            array($rememberMeToken, "is_granted('ROLE_FOO')", false),
            array($rememberMeToken, "is_granted('ROLE_USER')", true),

            array($usernamePasswordToken, 'is_anonymous()', false),
            array($usernamePasswordToken, 'is_authenticated()', true),
            array($usernamePasswordToken, 'is_fully_authenticated()', true),
            array($usernamePasswordToken, 'is_remember_me()', false),
            array($usernamePasswordToken, "is_granted('ROLE_FOO')", false),
            array($usernamePasswordToken, "is_granted('ROLE_USER')", true),
        );
    }

    /**
     * @dataProvider provideLegacyHasRole
     * @group legacy
     */
    public function testLegacyHasRole($expression, $result, $roles = array())
    {
        $expressionLanguage = new ExpressionLanguage();
        $context = array('roles' => $roles);

        $this->assertEquals($result, $expressionLanguage->evaluate($expression, $context));
    }

    public function provideLegacyHasRole()
    {
        $roles = array('ROLE_USER', 'ROLE_ADMIN');

        return array(
            array("has_role('ROLE_FOO')", false),
            array("has_role('ROLE_USER')", false),
            array("has_role('ROLE_ADMIN')", false),
            array("has_role('ROLE_FOO')", false, $roles),
            array("has_role('ROLE_USER')", true, $roles),
            array("has_role('ROLE_ADMIN')", true, $roles),
        );
    }
}
