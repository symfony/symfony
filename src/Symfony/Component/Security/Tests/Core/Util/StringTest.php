<?php

namespace Symfony\Component\Security\Tests\Core\Util;

use Symfony\Component\Security\Core\Util\StringUtils;

class StringTest extends \PHPUnit_Framework_TestCase
{
    public function testEquals()
    {
        $this->assertTrue(StringUtils::equals('password', 'password'));
        $this->assertFalse(StringUtils::equals('password', 'foo'));
    }
}
