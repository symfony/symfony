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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ClassesToCompilePass;

class ClassesToCompilePassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The "foo" extension cannot add "Vendor\FooBundle\VendorFooBundle", "Symfony\Component\Form\FormInterface" classes to the class cache, because it is necessary to know the path to the original file.
     */
    public function testInvalidClasses()
    {
        $extension = $this->getMock('Symfony\Component\HttpKernel\DependencyInjection\Extension');
        $extension->expects($this->once())->method('getClassesToCompile')->will($this->returnValue(array(
            'Symfony\Component\Form\FormInterface',
            'Vendor\FooBundle\VendorFooBundle',
            'Vendor\FooBundle\Model\Model',
        )));
        $extension->expects($this->once())->method('getAlias')->will($this->returnValue('foo'));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->expects($this->once())->method('getParameter')->with('kernel.bundles')->will($this->returnValue(array(
            'VendorFooBundle' => 'Vendor\FooBundle\VendorFooBundle',
        )));
        $container->expects($this->once())->method('getExtensions')->will($this->returnValue(array(
            $this->getMock('Symfony\Component\DependencyInjection\Extension\ExtensionInterface'),
                $extension,
        )));

        $pass = new ClassesToCompilePass();
        $pass->process($container);
    }
}
