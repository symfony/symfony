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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * @group legacy
 */
class AnonymousTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new AnonymousToken('foo', 'bar', ['ROLE_FOO']);
        self::assertEquals(['ROLE_FOO'], $token->getRoleNames());
    }

    public function testIsAuthenticated()
    {
        $token = new AnonymousToken('foo', 'bar');
        self::assertTrue($token->isAuthenticated());
    }

    public function testGetKey()
    {
        $token = new AnonymousToken('foo', 'bar');
        self::assertEquals('foo', $token->getSecret());
    }

    public function testGetCredentials()
    {
        $token = new AnonymousToken('foo', 'bar');
        self::assertEquals('', $token->getCredentials());
    }

    public function testGetUser()
    {
        $token = new AnonymousToken('foo', 'bar');
        self::assertEquals('bar', $token->getUser());
    }
}
