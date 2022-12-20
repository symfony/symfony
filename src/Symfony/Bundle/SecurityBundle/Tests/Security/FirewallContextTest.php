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
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class FirewallContextTest extends TestCase
{
    public function testGetters()
    {
        $config = new FirewallConfig('main', 'user_checker', 'request_matcher');
        $exceptionListener = $this->getExceptionListenerMock();
        $logoutListener = $this->getLogoutListenerMock();
        $listeners = [function () {}];

        $context = new FirewallContext($listeners, $exceptionListener, $logoutListener, $config);

        self::assertEquals($listeners, $context->getListeners());
        self::assertEquals($exceptionListener, $context->getExceptionListener());
        self::assertEquals($logoutListener, $context->getLogoutListener());
        self::assertEquals($config, $context->getConfig());
    }

    private function getExceptionListenerMock()
    {
        return self::getMockBuilder(ExceptionListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getLogoutListenerMock()
    {
        return self::getMockBuilder(LogoutListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
