<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symphony\Bundle\SecurityBundle\Security\FirewallContext;
use Symphony\Component\Security\Http\Firewall\ExceptionListener;
use Symphony\Component\Security\Http\Firewall\ListenerInterface;

class FirewallContextTest extends TestCase
{
    public function testGetters()
    {
        $config = new FirewallConfig('main', 'user_checker', 'request_matcher');
        $exceptionListener = $this->getExceptionListenerMock();
        $listeners = array(
            $this
                ->getMockBuilder(ListenerInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
        );

        $context = new FirewallContext($listeners, $exceptionListener, $config);

        $this->assertEquals($listeners, $context->getListeners());
        $this->assertEquals($exceptionListener, $context->getExceptionListener());
        $this->assertEquals($config, $context->getConfig());
    }

    private function getExceptionListenerMock()
    {
        return $this
            ->getMockBuilder(ExceptionListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
