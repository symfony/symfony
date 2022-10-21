<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Role;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class LegacyRoleTest extends TestCase
{
    public function testPayloadFromV4CanBeUnserialized()
    {
        $serialized = 'C:74:"Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken":236:{a:3:{i:0;N;i:1;s:4:"main";i:2;a:5:{i:0;s:2:"sf";i:1;b:1;i:2;a:1:{i:0;O:41:"Symfony\Component\Security\Core\Role\Role":1:{s:47:"Symfony\Component\Security\Core\Role\Role'."\0".'role'."\0".'";s:9:"ROLE_USER";}}i:3;a:0:{}i:4;a:1:{i:0;s:9:"ROLE_USER";}}}}';

        $token = unserialize($serialized);

        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertSame(['ROLE_USER'], $token->getRoleNames());
    }
}
