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

    /**
     * @expectedDeprecation Method Symfony\Bundle\SecurityBundle\Security\FirewallContext::getContext() is deprecated since version 3.3 and will be removed in 4.0. Use Symfony\Bundle\SecurityBundle\Security\FirewallContext::getListeners/getExceptionListener() instead.
     * @group legacy
     */
    public function testGetContext()
    {
        $context = (new FirewallContext($listeners = array(), $exceptionListener = $this->getExceptionListenerMock(), new FirewallConfig('main', 'request_matcher', 'user_checker')))
            ->getContext();

        $this->assertEquals(array($listeners, $exceptionListener), $context);
    }

    private function getExceptionListenerMock()
    {
        return $this
            ->getMockBuilder(ExceptionListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
