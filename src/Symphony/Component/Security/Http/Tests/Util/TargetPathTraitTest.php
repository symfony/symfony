<?php

namespace Symphony\Component\Security\Http\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Session\SessionInterface;
use Symphony\Component\Security\Http\Util\TargetPathTrait;

class TargetPathTraitTest extends TestCase
{
    public function testSetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symphony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('set')
            ->with('_security.firewall_name.target_path', '/foo');

        $obj->doSetTargetPath($session, 'firewall_name', '/foo');
    }

    public function testGetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symphony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('get')
            ->with('_security.cool_firewall.target_path')
            ->willReturn('/bar');

        $actualUri = $obj->doGetTargetPath($session, 'cool_firewall');
        $this->assertEquals(
            '/bar',
            $actualUri
        );
    }

    public function testRemoveTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->getMockBuilder('Symphony\Component\HttpFoundation\Session\SessionInterface')
                    ->getMock();

        $session->expects($this->once())
            ->method('remove')
            ->with('_security.best_firewall.target_path');

        $obj->doRemoveTargetPath($session, 'best_firewall');
    }
}

class TestClassWithTargetPathTrait
{
    use TargetPathTrait;

    public function doSetTargetPath(SessionInterface $session, $providerKey, $uri)
    {
        $this->saveTargetPath($session, $providerKey, $uri);
    }

    public function doGetTargetPath(SessionInterface $session, $providerKey)
    {
        return $this->getTargetPath($session, $providerKey);
    }

    public function doRemoveTargetPath(SessionInterface $session, $providerKey)
    {
        $this->removeTargetPath($session, $providerKey);
    }
}
