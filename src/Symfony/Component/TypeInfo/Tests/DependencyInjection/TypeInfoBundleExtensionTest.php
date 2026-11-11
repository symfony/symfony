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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\TypeInfo\Refinement;
use Symfony\Component\TypeInfo\TypeInfoBundle;

class TypeInfoBundleExtensionTest extends TestCase
{
    public function testTypeInfoEnabled()
    {
        $container = $this->createContainerFromFile('type_info');

        $this->assertTrue($container->has('type_info.resolver'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
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

    public function testRefinementsArePassedToTheStringResolver()
    {
        if (!class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" dependency is required.');
        }

        $this->assertTrue($this->createContainerFromFile('type_info')->getDefinition('type_info.resolver.string')->getArgument(3));

        $container = $this->createContainer();
        $container->loadFromExtension('type_info', ['refinements' => ['int_range']]);
        $container->compile();

        $this->assertSame([Refinement::IntRange], $container->getDefinition('type_info.resolver.string')->getArgument(3));

        $container = $this->createContainer();
        $container->loadFromExtension('type_info', ['refinements' => false]);
        $container->compile();

        $this->assertFalse($container->getDefinition('type_info.resolver.string')->getArgument(3));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotSettingRefinementsIsDeprecated()
    {
        if (!class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" dependency is required.');
        }

        $this->expectUserDeprecationMessage('Since symfony/type-info 8.2: Not setting the "type_info.refinements" option is deprecated; all PHPDoc refinements will be resolved by default in 9.0. Set it to true to resolve them now, or to false to keep their builtin types.');

        // the services still get an explicit value, so they do not trigger deprecations of their own
        $this->assertFalse($this->createContainerFromFile('type_info_default_config')->getDefinition('type_info.resolver.string')->getArgument(3));
    }

    public function testRefinementsMustBeKnown()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('type_info', ['refinements' => ['literal', 'nope']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "refinements" option must be a boolean or a list of "literal", "int_range", ["literal","nope"] given.');

        $container->compile();
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
