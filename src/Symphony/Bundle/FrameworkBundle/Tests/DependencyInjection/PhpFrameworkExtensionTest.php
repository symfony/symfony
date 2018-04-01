<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symphony\Component\Config\FileLocator;

class PhpFrameworkExtensionTest extends FrameworkExtensionTest
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures/php'));
        $loader->load($file.'.php');
    }

    /**
     * @expectedException \LogicException
     */
    public function testAssetsCannotHavePathAndUrl()
    {
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', array(
                'assets' => array(
                    'base_urls' => 'http://cdn.example.com',
                    'base_path' => '/foo',
                ),
            ));
        });
    }

    /**
     * @expectedException \LogicException
     */
    public function testAssetPackageCannotHavePathAndUrl()
    {
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', array(
                'assets' => array(
                    'packages' => array(
                        'impossible' => array(
                            'base_urls' => 'http://cdn.example.com',
                            'base_path' => '/foo',
                        ),
                    ),
                ),
            ));
        });
    }
}
