<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\DependencyInjection;

use Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionAbsentWithConfigBundle\ExtensionAbsentWithConfigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutoloadExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetAlias()
    {
        $bundle = new ExtensionAbsentWithConfigBundle();
        $ext = $bundle->getContainerExtension();
        $this->assertEquals('extension_absent_with_config', $ext->getAlias());
    }

    /**
     * @dataProvider extensionProvider
     */
    public function testAutoloadExtension($class, $expected)
    {
        $bundle = new $class;
        $ext = $bundle->getContainerExtension();
        $this->assertInstanceOf('Symfony\Component\HttpKernel\DependencyInjection\AutoloadExtension', $ext);

        $builder = new ContainerBuilder;
        $ext->load(array(array(), array()), $builder);
        $this->assertEquals($expected, $builder->hasDefinition('foo'));
    }

    public function extensionProvider()
    {
        return array(
            'with file must import'     => array(
                'Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionAbsentWithConfigBundle\ExtensionAbsentWithConfigBundle',
                true
            ),
            'without file must ignore'  => array(
                'Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle',
                false
            ),
        );
    }
}
