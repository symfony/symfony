<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Security\Acl\Permission;

use Symfony\Component\Security\Acl\Permission\BasicPermissionMap;

class BasicPermissionMapTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMasksReturnsNullWhenNotSupportedMask()
    {
        $map = new BasicPermissionMap();
        $this->assertNull($map->getMasks('IS_AUTHENTICATED_REMEMBERED', null));
    }
}
