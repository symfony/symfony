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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UsernamePasswordTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertEquals('key', $token->getFirewallName());
    }

    public function testToString()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', '', ['A', 'B']), 'foo', ['A', 'B']);
        $this->assertEquals('UsernamePasswordToken(user="foo", roles="A, B")', (string) $token);
    }
}
