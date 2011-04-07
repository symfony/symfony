<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

class MergeExtensionConfigurationPassTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoloadMainExtension()
    {
        $bundles = array(
            'ExtensionAbsentBundle'  => 'Symfony\\Tests\\Component\\HttpKernel\\Fixtures\\ExtensionAbsentBundle\\ExtensionAbsentBundle',
            'ExtensionLoadedBundle'  => 'Symfony\\Tests\\Component\\HttpKernel\\Fixtures\\ExtensionLoadedBundle\\ExtensionLoadedBundle',
            'ExtensionPresentBundle' => 'Symfony\\Tests\\Component\\HttpKernel\\Fixtures\\ExtensionPresentBundle\\ExtensionPresentBundle',
        );

        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerBuilder');
        $params = $this->getMock('Symfony\\Component\\DependencyInjection\\ParameterBag\\ParameterBag');

        $container->expects($this->once())
            ->method('getParameter')
            ->with('kernel.bundles')
            ->will($this->returnValue($bundles));
        $container->expects($this->exactly(2))
            ->method('getExtensionConfig')
            ->will($this->returnCallback(function($name) {
                switch ($name) {
                    case 'extension_present':
                    return array();
                    case 'extension_loaded':
                    return array(array());
                }
            }));
        $container->expects($this->once())
            ->method('loadFromExtension')
            ->with('extension_present', array());

        $container->expects($this->any())
            ->method('getParameterBag')
            ->will($this->returnValue($params));
        $params->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));
        $container->expects($this->any())
            ->method('getDefinitions')
            ->will($this->returnValue(array()));
        $container->expects($this->any())
            ->method('getAliases')
            ->will($this->returnValue(array()));
        $container->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array()));

        $configPass = new MergeExtensionConfigurationPass();
        $configPass->process($container);
    }
}
