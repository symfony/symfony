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
        $token = new RememberMeToken($user, 'fookey');

        $this->assertEquals('fookey', $token->getFirewallName());
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertSame($user, $token->getUser());
    }

    /**
     * @group legacy
     */
    public function testSecret()
    {
        $user = $this->getUser();
        $token = new RememberMeToken($user, 'fookey', 'foo');

        $this->assertEquals('foo', $token->getSecret());
    }

    protected function getUser($roles = ['ROLE_FOO'])
    {
        $user = $this->createMock(UserInterface::class);
        $user
            ->expects($this->any())
            ->method('getRoles')
            ->willReturn($roles)
        ;

        return $user;
    }
}
