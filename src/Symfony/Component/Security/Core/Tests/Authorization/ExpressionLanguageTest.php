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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\User\User;

class ExpressionLanguageTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testIsAuthenticated($token, $expression, $result, array $roles = [])
    {
        $anonymousTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\AnonymousToken';
        $rememberMeTokenClass = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\RememberMeToken';
        $expressionLanguage = new ExpressionLanguage();
        $trustResolver = new AuthenticationTrustResolver($anonymousTokenClass, $rememberMeTokenClass);

        $context = [];
        $context['trust_resolver'] = $trustResolver;
        $context['token'] = $token;
        $context['roles'] = $roles;

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
            [$noToken, "has_role('ROLE_USER')", false],

            [$anonymousToken, 'is_anonymous()', true],
            [$anonymousToken, 'is_authenticated()', false],
            [$anonymousToken, 'is_fully_authenticated()', false],
            [$anonymousToken, 'is_remember_me()', false],
            [$anonymousToken, "has_role('ROLE_USER')", false],

            [$rememberMeToken, 'is_anonymous()', false],
            [$rememberMeToken, 'is_authenticated()', true],
            [$rememberMeToken, 'is_fully_authenticated()', false],
            [$rememberMeToken, 'is_remember_me()', true],
            [$rememberMeToken, "has_role('ROLE_FOO')", false, $roles],
            [$rememberMeToken, "has_role('ROLE_USER')", true, $roles],

            [$usernamePasswordToken, 'is_anonymous()', false],
            [$usernamePasswordToken, 'is_authenticated()', true],
            [$usernamePasswordToken, 'is_fully_authenticated()', true],
            [$usernamePasswordToken, 'is_remember_me()', false],
            [$usernamePasswordToken, "has_role('ROLE_FOO')", false, $roles],
            [$usernamePasswordToken, "has_role('ROLE_USER')", true, $roles],
        ];
    }
}
