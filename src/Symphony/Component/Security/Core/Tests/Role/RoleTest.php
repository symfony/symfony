<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Role;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Role\Role;

class RoleTest extends TestCase
{
    public function testGetRole()
    {
        $role = new Role('FOO');

        $this->assertEquals('FOO', $role->getRole());
    }
}
