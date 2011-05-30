<?php

namespace Symfony\Tests\Component\Security\Acl\Permission;

use Symfony\Component\Security\Acl\Permission\BasicPermissionMap;

class BasicPermissionMapTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMasksReturnsNullWhenNotSupportedMask()
    {
        $map = new BasicPermissionMap();
        $this->assertNull($map->getMasks('IS_AUTHENTICATED_REMEBERED', null));
    }
}