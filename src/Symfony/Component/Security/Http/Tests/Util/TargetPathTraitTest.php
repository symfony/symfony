<?php

namespace Symfony\Component\Security\Http\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class TargetPathTraitTest extends TestCase
{
    public function testSetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('set')
            ->with('_security.firewall_name.target_path', '/foo');

        $obj->saveTargetPath($session, 'firewall_name', '/foo');
    }

    public function testGetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('get')
            ->with('_security.cool_firewall.target_path')
            ->willReturn('/bar');

        $actualUri = $obj->getTargetPath($session, 'cool_firewall');
        $this->assertEquals(
            '/bar',
            $actualUri
        );
    }

    public function testRemoveTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('remove')
            ->with('_security.best_firewall.target_path');

        $obj->removeTargetPath($session, 'best_firewall');
    }
}

class TestClassWithTargetPathTrait
{
    use TargetPathTrait {
        saveTargetPath as public;
        getTargetPath as public;
        removeTargetPath as public;
    }
}
