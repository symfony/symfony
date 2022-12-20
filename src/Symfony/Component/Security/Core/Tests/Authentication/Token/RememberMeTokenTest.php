<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\User\UserInterface;

class RememberMeTokenTest extends TestCase
{
    public function testConstructor()
    {
        $user = $this->getUser();
        $token = new RememberMeToken($user, 'fookey', 'foo');

        self::assertEquals('fookey', $token->getFirewallName());
        self::assertEquals('foo', $token->getSecret());
        self::assertEquals(['ROLE_FOO'], $token->getRoleNames());
        self::assertSame($user, $token->getUser());
    }

    /**
     * @group legacy
     */
    public function testIsAuthenticated()
    {
        $user = $this->getUser();
        $token = new RememberMeToken($user, 'fookey', 'foo');
        self::assertTrue($token->isAuthenticated());
    }

    public function testConstructorSecretCannotBeEmptyString()
    {
        self::expectException(\InvalidArgumentException::class);
        new RememberMeToken(
            $this->getUser(),
            '',
            ''
        );
    }

    protected function getUser($roles = ['ROLE_FOO'])
    {
        $user = self::createMock(UserInterface::class);
        $user
            ->expects(self::any())
            ->method('getRoles')
            ->willReturn($roles)
        ;

        return $user;
    }
}
