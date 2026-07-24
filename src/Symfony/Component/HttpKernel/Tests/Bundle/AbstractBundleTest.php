<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Bundle;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Tests\Fixtures\AcmeBarBundle\AcmeBarBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\AcmeFooBundle\AcmeFooBundle;

class AbstractBundleTest extends TestCase
{
    public function testExtensionNamespaceDefaultsToTheExtensionNamespace()
    {
        $bundle = new AcmeFooBundle();

        self::assertSame('http://example.org/schema/dic/acme_foo', $bundle->getContainerExtension()->getNamespace());
    }

    public function testExtensionNamespaceIsForwardedFromBundle()
    {
        $bundle = new AcmeBarBundle();

        self::assertSame('http://acme.example/schema/dic/acme_bar', $bundle->getContainerExtension()->getNamespace());
    }

    public function testConfigurationIsLoadedFromXmlUsingTheBundleNamespace()
    {
        $bundle = new AcmeBarBundle();

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
        ]));
        $container->registerExtension($bundle->getContainerExtension());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/AcmeBarBundle/Resources/config'));
        $loader->load('config.xml');

        $container->compile();

        self::assertTrue($container->hasParameter('acme_bar.config'));
        self::assertSame(['foo' => 'hello'], $container->getParameter('acme_bar.config'));
    }
}
