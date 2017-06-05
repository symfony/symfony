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
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;

class FirewallMapTest extends \PHPUnit_Framework_TestCase
{
    public function testGetFirewallConfigByName()
    {
        $config = new FirewallConfig('foo', 'bar', 'baz');

        $context = $this->getMockBuilder('Symfony\Bundle\SecurityBundle\Security\FirewallContext')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $context
            ->expects($this->once())
            ->method('getConfig')
            ->will($this->returnValue($config))
        ;

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('security.firewall.map.context.foo'))
            ->will($this->returnValue($context))
        ;

        $map = new FirewallMap($container, array(
            'security.firewall.map.context.foo' => $this->getMock('Symfony\Component\HttpFoundation\RequestMatcher'),
            'security.firewall.map.context.bar' => $this->getMock('Symfony\Component\HttpFoundation\RequestMatcher'),
        ));

        $this->assertSame($config, $map->getFirewallConfigByName('foo'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetFirewallConfigByNameWithUnknownName()
    {
        $map = new FirewallMap(
            $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface'),
            array()
        );

        $map->getFirewallConfigByName('foo');
    }
}
