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
    public function testIsAuthenticated($token, $expression, $result, array $roles = array())
    {
        $anonymousTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\AnonymousToken';
        $rememberMeTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\RememberMeToken';
        $expressionLanguage = new ExpressionLanguage();
        $trustResolver = new AuthenticationTrustResolver($anonymousTokenClass, $rememberMeTokenClass);

        $context = array();
        $context['trust_resolver'] = $trustResolver;
        $context['token'] = $token;
        $context['roles'] = $roles;

        $this->assertEquals($result, $expressionLanguage->evaluate($expression, $context));
    }

    /**
     * @dataProvider provider
     */
    public function testIsAuthenticatedWithAuthorizationChecker($token, $expression, $result, array $roles = array())
    {
        $anonymousTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\AnonymousToken';
        $rememberMeTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\RememberMeToken';
        $expressionLanguage = new ExpressionLanguage();
        $trustResolver = new AuthenticationTrustResolver($anonymousTokenClass, $rememberMeTokenClass);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $authorizationChecker = new AuthorizationChecker($tokenStorage, $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock(), new AccessDecisionManager(array(new RoleVoter())));

        $context = array();
        $context['auth_checker'] = $authorizationChecker;
        $context['trust_resolver'] = $trustResolver;
        $context['token'] = $token;
        $context['roles'] = $roles;

        $this->assertSame($result, $expressionLanguage->evaluate($expression, $context));
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
            array($noToken, "has_role('ROLE_USER')", false),

            array($anonymousToken, 'is_anonymous()', true),
            array($anonymousToken, 'is_authenticated()', false),
            array($anonymousToken, 'is_fully_authenticated()', false),
            array($anonymousToken, 'is_remember_me()', false),
            array($anonymousToken, "has_role('ROLE_USER')", false),

            array($rememberMeToken, 'is_anonymous()', false),
            array($rememberMeToken, 'is_authenticated()', true),
            array($rememberMeToken, 'is_fully_authenticated()', false),
            array($rememberMeToken, 'is_remember_me()', true),
            array($rememberMeToken, "has_role('ROLE_FOO')", false, $roles),
            array($rememberMeToken, "has_role('ROLE_USER')", true, $roles),

            array($usernamePasswordToken, 'is_anonymous()', false),
            array($usernamePasswordToken, 'is_authenticated()', true),
            array($usernamePasswordToken, 'is_fully_authenticated()', true),
            array($usernamePasswordToken, 'is_remember_me()', false),
            array($usernamePasswordToken, "has_role('ROLE_FOO')", false, $roles),
            array($usernamePasswordToken, "has_role('ROLE_USER')", true, $roles),
        );
    }

    public function testHasRoleTriggersTokenAuthentication()
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider', array('ROLE_ADMIN'));
        $refreshedToken = new UsernamePasswordToken('username', 'password', 'provider', array('ROLE_USER'));

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $authenticationManager->method('authenticate')->willReturn($refreshedToken);

        $authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, new AccessDecisionManager(array(new RoleVoter())), true);

        $context = array(
            'auth_checker' => $authorizationChecker,
            'trust_resolver' => $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface')->getMock(),
            'token' => $token,
            'roles' => array('ROLE_ADMIN'),
        );
        $expressionLanguage = new ExpressionLanguage();

        $this->assertFalse($expressionLanguage->evaluate('has_role("ROLE_ADMIN")', array(
            'auth_checker' => $authorizationChecker,
            'trust_resolver' => $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface')->getMock(),
            'token' => $token,
            'roles' => array('ROLE_ADMIN'),
        )));
    }
}
