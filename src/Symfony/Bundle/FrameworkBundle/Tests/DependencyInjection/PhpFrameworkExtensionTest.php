<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

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
