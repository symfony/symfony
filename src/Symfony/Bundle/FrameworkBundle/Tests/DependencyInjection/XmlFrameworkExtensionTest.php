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

class XmlFrameworkExtensionTest extends FrameworkExtensionTest
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml'));
        $loader->load($file.'.xml');
    }

    public function testAssetsHelperIsRemovedWhenPhpTemplatingEngineIsEnabledAndAssetsAreDisabled()
    {
        self::markTestSkipped('The assets key cannot be set to false using the XML configuration format.');
    }

    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        self::markTestSkipped('XML configuration will not allow erroneous format.');
    }

    public function testLegacyExceptionsConfig()
    {
        $container = $this->createContainerFromFile('exceptions_legacy');

        $configuration = $container->getDefinition('exception_listener')->getArgument(3);

        self::assertSame([
            \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ConflictHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class,
        ], array_keys($configuration));

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => 422,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => null,
            'status_code' => 500,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class]);
    }
}
