<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class TargetPathTraitTest extends TestCase
{
    public function testSetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('set')
            ->with('_security.firewall_name.target_path', '/foo');

        $obj->doSetTargetPath($session, 'firewall_name', '/foo');
    }

    public function testGetTargetPath()
    {
        $obj = new TestClassWithTargetPathTrait();

        $session = $this->createMock(SessionInterface::class);
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

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('remove')
            ->with('_security.best_firewall.target_path');

        $obj->doRemoveTargetPath($session, 'best_firewall');
    }
}

class TestClassWithTargetPathTrait
{
    use TargetPathTrait;

    public function doSetTargetPath(SessionInterface $session, $firewallName, $uri)
    {
        $this->saveTargetPath($session, $firewallName, $uri);
    }

    public function doGetTargetPath(SessionInterface $session, $firewallName)
    {
        return $this->getTargetPath($session, $firewallName);
    }

    public function doRemoveTargetPath(SessionInterface $session, $firewallName)
    {
        $this->removeTargetPath($session, $firewallName);
    }
}
