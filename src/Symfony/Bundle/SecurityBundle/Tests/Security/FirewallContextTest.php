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

use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class FirewallContextTest extends \PHPUnit_Framework_TestCase
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

        $this->assertEquals(array($listeners, $exceptionListener), $context->getListeners());
        $this->assertEquals($config, $context->getConfig());
    }

    /**
     * @expectedDeprecation Method Symfony\Bundle\SecurityBundle\Security\FirewallContext::getContext() is deprecated since version 3.3 and will be removed in 4.0. Use Symfony\Bundle\SecurityBundle\Security\FirewallContext::getListeners() instead.
     * @group legacy
     */
    public function testGetContextTriggersDeprecation()
    {
        (new FirewallContext(array(), $this->getExceptionListenerMock(), new FirewallConfig('main', 'request_matcher', 'user_checker')))
            ->getContext();
    }

    private function getExceptionListenerMock()
    {
        return $this
            ->getMockBuilder(ExceptionListener::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
