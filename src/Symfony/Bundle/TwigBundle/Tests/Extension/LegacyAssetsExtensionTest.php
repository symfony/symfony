<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Extension;

use Symfony\Bundle\TwigBundle\Extension\AssetsExtension;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\Routing\RequestContext;

class LegacyAssetsExtensionTest extends TestCase
{
    /**
     * @dataProvider provideGetGetAssetUrlArguments
     */
    public function testGetAssetUrl($path, $packageName, $absolute, $relativeUrl, $expectedUrl, $scheme, $host, $httpPort, $httpsPort)
    {
        $helper = $this->createHelperMock($path, $packageName, $relativeUrl);
        $container = $this->createContainerMock($helper);

        $context = $this->createRequestContextMock($scheme, $host, $httpPort, $httpsPort);

        $extension = new AssetsExtension($container, $context);
        $this->assertEquals($expectedUrl, $extension->getAssetUrl($path, $packageName, $absolute));
    }

    public function testGetAssetWithoutHost()
    {
        $path = '/path/to/asset';
        $packageName = null;
        $relativeUrl = '/bundle-name/path/to/asset';

        $helper = $this->createHelperMock($path, $packageName, $relativeUrl);
        $container = $this->createContainerMock($helper);

        $context = $this->createRequestContextMock('http', '', 80, 443);

        $extension = new AssetsExtension($container, $context);
        $this->assertEquals($relativeUrl, $extension->getAssetUrl($path, $packageName, true));
    }

    public function provideGetGetAssetUrlArguments()
    {
        return array(
            array('/path/to/asset', 'package-name', false, '/bundle-name/path/to/asset', '/bundle-name/path/to/asset', 'http', 'symfony.com', 80, null),
            array('/path/to/asset', 'package-name', false, 'http://subdomain.symfony.com/bundle-name/path/to/asset', 'http://subdomain.symfony.com/bundle-name/path/to/asset', 'http', 'symfony.com', 80, null),
            array('/path/to/asset', null, false, '/bundle-name/path/to/asset', '/bundle-name/path/to/asset', 'http', 'symfony.com', 80, null),
            array('/path/to/asset', 'package-name', true, '/bundle-name/path/to/asset', 'http://symfony.com/bundle-name/path/to/asset', 'http', 'symfony.com', 80, null),
            array('/path/to/asset', 'package-name', true, 'http://subdomain.symfony.com/bundle-name/path/to/asset', 'http://subdomain.symfony.com/bundle-name/path/to/asset', 'http', 'symfony.com', 80, null),
            array('/path/to/asset', null, true, '/bundle-name/path/to/asset', 'https://symfony.com:92/bundle-name/path/to/asset', 'https', 'symfony.com', null, 92),
            array('/path/to/asset', null, true, '/bundle-name/path/to/asset', 'http://symfony.com:660/bundle-name/path/to/asset', 'http', 'symfony.com', 660, null),
        );
    }

    private function createRequestContextMock($scheme, $host, $httpPort, $httpsPort)
    {
        $context = $this->getMockBuilder('Symfony\Component\Routing\RequestContext')
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects($this->any())
            ->method('getScheme')
            ->will($this->returnValue($scheme));
        $context->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue($host));
        $context->expects($this->any())
            ->method('getHttpPort')
            ->will($this->returnValue($httpPort));
        $context->expects($this->any())
            ->method('getHttpsPort')
            ->will($this->returnValue($httpsPort));

        return $context;
    }

    private function createContainerMock($helper)
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())
            ->method('get')
            ->with('templating.helper.assets')
            ->will($this->returnValue($helper));

        return $container;
    }

    private function createHelperMock($path, $packageName, $returnValue)
    {
        $helper = $this->getMockBuilder('Symfony\Component\Templating\Helper\CoreAssetsHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $helper->expects($this->any())
            ->method('getUrl')
            ->with($path, $packageName)
            ->will($this->returnValue($returnValue));

        return $helper;
    }
}
