<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;

class UnusedTagsPassTest extends TestCase
{
    public function testProcess()
    {
        $pass = new UnusedTagsPass();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds', 'findUnusedTags', 'findTags', 'log'))->getMock();
        $container->expects($this->once())
            ->method('log')
            ->with($pass, 'Tag "kenrel.event_subscriber" was defined on service(s) "foo", "bar", but was never used. Did you mean "kernel.event_subscriber"?');
        $container->expects($this->once())
            ->method('findTags')
            ->will($this->returnValue(array('kenrel.event_subscriber')));
        $container->expects($this->once())
            ->method('findUnusedTags')
            ->will($this->returnValue(array('kenrel.event_subscriber', 'form.type')));
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('kenrel.event_subscriber')
            ->will($this->returnValue(array(
                'foo' => array(),
                'bar' => array(),
            )));

        $pass->process($container);
    }
}
