<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\TargetPathHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TargetPathHelperTest extends TestCase
{
    public function testSavePath()
    {
        $session = $this->createMock(SessionInterface::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $requestStack = $this->createMock(RequestStack::class);
        $request = new Request();
        $requestStack->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn($request);
        $firewallConfig = new FirewallConfig('firewall_name', '');
        $firewallMap->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($firewallConfig);
        $session->expects($this->once())
            ->method('set')
            ->with('_security.firewall_name.target_path', '/foo');
        $targetPathHelper = new TargetPathHelper($session, $firewallMap, $requestStack);
        $targetPathHelper->savePath('/foo');
    }
}
