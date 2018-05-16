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
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class FirewallContextTest extends TestCase
{
    public function testGetters()
    {
        $config = new FirewallConfig('main', 'user_checker', 'request_matcher');
        $exceptionListener = $this->getExceptionListenerMock();
        $logoutListener = $this->getLogoutListenerMock();
        $listeners = array(
            $this
                ->getMockBuilder(ListenerInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
        );

        $context = new FirewallContext($listeners, $exceptionListener, $logoutListener, $config);

        $this->assertEquals($listeners, $context->getListeners());
        $this->assertEquals($exceptionListener, $context->getExceptionListener());
        $this->assertEquals($logoutListener, $context->getLogoutListener());
        $this->assertEquals($config, $context->getConfig());
    }

    private function getExceptionListenerMock()
    {
        return $this
            ->getMockBuilder(ExceptionListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getLogoutListenerMock()
    {
        return $this
            ->getMockBuilder(LogoutListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
