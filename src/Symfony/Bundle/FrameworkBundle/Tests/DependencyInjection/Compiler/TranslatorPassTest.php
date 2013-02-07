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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;

class TranslatorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testValidCollector()
    {
        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $definition->expects($this->at(0))
            ->method('addMethodCall')
            ->with('addLoader', array('xliff', new Reference('xliff')));
        $definition->expects($this->at(1))
            ->method('addMethodCall')
            ->with('addLoader', array('xlf', new Reference('xliff')));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));
        $container->expects($this->once())
            ->method('getDefinition')
            ->will($this->returnValue($definition));
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array('xliff' => array(array('alias' => 'xliff', 'legacy-alias' => 'xlf')))));
        $container->expects($this->once())
            ->method('findDefinition')
            ->will($this->returnValue($this->getMock('Symfony\Component\DependencyInjection\Definition')));
        ;

        $pass = new TranslatorPass();
        $pass->process($container);
    }
}
