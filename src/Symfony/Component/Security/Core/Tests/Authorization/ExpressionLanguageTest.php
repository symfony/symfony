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
        $accessDecisionManager = new AccessDecisionManager([new RoleVoter()]);
        $authChecker = new AuthorizationChecker($tokenStorage, $this->getMockBuilder(AuthenticationManagerInterface::class)->getMock(), $accessDecisionManager);

        $context = [];
        $context['trust_resolver'] = $trustResolver;
        $context['auth_checker'] = $authChecker;
        $context['token'] = $token;

        $this->assertEquals($result, $expressionLanguage->evaluate($expression, $context));
    }

    public function provider()
    {
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $user = new User('username', 'password', $roles);

        $noToken = null;
        $anonymousToken = new AnonymousToken('firewall', 'anon.');
        $rememberMeToken = new RememberMeToken($user, 'providerkey', 'firewall');
        $usernamePasswordToken = new UsernamePasswordToken('username', 'password', 'providerkey', $roles);

        return [
            [$noToken, 'is_anonymous()', false],
            [$noToken, 'is_authenticated()', false],
            [$noToken, 'is_fully_authenticated()', false],
            [$noToken, 'is_remember_me()', false],

            [$anonymousToken, 'is_anonymous()', true],
            [$anonymousToken, 'is_authenticated()', false],
            [$anonymousToken, 'is_fully_authenticated()', false],
            [$anonymousToken, 'is_remember_me()', false],
            [$anonymousToken, "is_granted('ROLE_USER')", false],

            [$rememberMeToken, 'is_anonymous()', false],
            [$rememberMeToken, 'is_authenticated()', true],
            [$rememberMeToken, 'is_fully_authenticated()', false],
            [$rememberMeToken, 'is_remember_me()', true],
            [$rememberMeToken, "is_granted('ROLE_FOO')", false],
            [$rememberMeToken, "is_granted('ROLE_USER')", true],

            [$usernamePasswordToken, 'is_anonymous()', false],
            [$usernamePasswordToken, 'is_authenticated()', true],
            [$usernamePasswordToken, 'is_fully_authenticated()', true],
            [$usernamePasswordToken, 'is_remember_me()', false],
            [$usernamePasswordToken, "is_granted('ROLE_FOO')", false],
            [$usernamePasswordToken, "is_granted('ROLE_USER')", true],
        ];
    }

    /**
     * @dataProvider provideLegacyHasRole
     * @group legacy
     */
    public function testLegacyHasRole($expression, $result, $roles = [])
    {
        $expressionLanguage = new ExpressionLanguage();
        $context = ['roles' => $roles];

        $this->assertEquals($result, $expressionLanguage->evaluate($expression, $context));
    }

    public function provideLegacyHasRole()
    {
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];

        return [
            ["has_role('ROLE_FOO')", false],
            ["has_role('ROLE_USER')", false],
            ["has_role('ROLE_ADMIN')", false],
            ["has_role('ROLE_FOO')", false, $roles],
            ["has_role('ROLE_USER')", true, $roles],
            ["has_role('ROLE_ADMIN')", true, $roles],
        ];
    }
}
