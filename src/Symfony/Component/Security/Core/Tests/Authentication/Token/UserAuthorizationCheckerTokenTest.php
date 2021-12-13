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
use Symfony\Component\Security\Core\Authentication\Token\UserAuthorizationCheckerToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserAuthorizationCheckerTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new UserAuthorizationCheckerToken($user = new InMemoryUser('foo', 'bar', ['ROLE_FOO']));
        $this->assertSame(['ROLE_FOO'], $token->getRoleNames());
        $this->assertSame($user, $token->getUser());
    }
}
