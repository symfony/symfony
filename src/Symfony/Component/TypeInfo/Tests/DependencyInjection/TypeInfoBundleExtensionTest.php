<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\DependencyInjection;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\TypeInfo\TypeInfoBundle;

class TypeInfoBundleExtensionTest extends TestCase
{
    public function testTypeInfoEnabled()
    {
        $container = $this->createContainerFromFile('type_info');

        $this->assertTrue($container->has('type_info.resolver'));
    }

    public function testTypeInfoEnabledWithoutConfiguration()
    {
        $container = $this->createContainerFromFile('type_info_default_config');

        $this->assertTrue($container->has('type_info.resolver'));
        $this->assertTrue($container->has('type_info.type_context_factory'));
    }

    public function testTypeInfoDisabled()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('type_info', ['enabled' => false]);
        $container->compile();

        $this->assertFalse($container->has('type_info.resolver'));
    }

    public function testTypeAliasesArePassedToTheContextFactory()
    {
        if (!class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" dependency is required.');
        }

        $container = $this->createContainerFromFile('type_info');

        $this->assertSame(['CustomAlias' => 'int'], $container->getDefinition('type_info.type_context_factory')->getArgument(1));
        $this->assertSame(['CustomAlias' => 'int'], $container->getDefinition('type_info.resolver.string')->getArgument(2));
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = $this->createContainer();
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $container->compile();

        return $container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new TypeInfoBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        return $container;
    }
}
