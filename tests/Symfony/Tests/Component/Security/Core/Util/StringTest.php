<?php

namespace Symfony\Tests\Component\Security\Core\Util;

use Symfony\Component\Security\Core\Util\String;

class StringTest extends \PHPUnit_Framework_TestCase
{
    public function testEquals()
    {
        $this->assertTrue(String::equals('password', 'password'));
        $this->assertFalse(String::equals('password', 'foo'));
    }
}