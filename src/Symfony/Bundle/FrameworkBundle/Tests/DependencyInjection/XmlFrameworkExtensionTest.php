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
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class XmlFrameworkExtensionTest extends FrameworkExtensionTestCase
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml'));
        $loader->load($file.'.xml');
    }

    public function testAssetsHelperIsRemovedWhenPhpTemplatingEngineIsEnabledAndAssetsAreDisabled()
    {
        $this->markTestSkipped('The assets key cannot be set to false using the XML configuration format.');
    }

    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        $this->markTestSkipped('XML configuration will not allow erroneous format.');
    }

    public function testLegacyExceptionsConfig()
    {
        $container = $this->createContainerFromFile('exceptions_legacy');

        $configuration = $container->getDefinition('exception_listener')->getArgument(3);

        $this->assertSame([
            \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ConflictHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class,
        ], array_keys($configuration));

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => 422,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => null,
            'status_code' => 500,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class]);
    }

    public function testRateLimiter()
    {
        $container = $this->createContainerFromFile('rate_limiter');

        $this->assertTrue($container->hasDefinition('limiter.sliding_window'));
    }

    public function testAssetMapper()
    {
        $container = $this->createContainerFromFile('asset_mapper');

        $definition = $container->getDefinition('asset_mapper.public_assets_path_resolver');
        $this->assertSame('/assets_path/', $definition->getArgument(0));

        $definition = $container->getDefinition('asset_mapper.dev_server_subscriber');
        $this->assertSame(['zip' => 'application/zip'], $definition->getArgument(2));

        $definition = $container->getDefinition('asset_mapper.importmap.renderer');
        $this->assertSame(['data-turbo-track' => 'reload'], $definition->getArgument(4));

        $definition = $container->getDefinition('asset_mapper.repository');
        $this->assertSame(['assets/' => '', 'assets2/' => 'my_namespace'], $definition->getArgument(0));

        $definition = $container->getDefinition('asset_mapper.compiler.css_asset_url_compiler');
        $this->assertSame('strict', $definition->getArgument(0));
    }
}
