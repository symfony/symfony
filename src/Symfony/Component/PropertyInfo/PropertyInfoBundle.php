<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\ContextFactory;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoConstructorPass;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;
use Symfony\Component\PropertyInfo\DependencyInjection\RemovePropertyInfoCachePass;
use Symfony\Component\PropertyInfo\Extractor\ConstructorArgumentTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;

/**
 * Provides the property info services.
 */
#[RequiredBundle(ServicesBundle::class)]
class PropertyInfoBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PropertyInfoPass());
        $container->addCompilerPass(new PropertyInfoConstructorPass());
        $container->addCompilerPass(new RemovePropertyInfoCachePass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->info('Property info configuration')
            ->canBeDisabled()
            ->children()
                ->booleanNode('with_constructor_extractor')
                    ->info('Registers the constructor extractor.')
                    ->defaultTrue()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/property_info.php');

        $container->registerForAutoconfiguration(PropertyListExtractorInterface::class)
            ->addTag('property_info.list_extractor');
        $container->registerForAutoconfiguration(PropertyTypeExtractorInterface::class)
            ->addTag('property_info.type_extractor');
        $container->registerForAutoconfiguration(ConstructorArgumentTypeExtractorInterface::class)
            ->addTag('property_info.constructor_extractor');
        $container->registerForAutoconfiguration(PropertyDescriptionExtractorInterface::class)
            ->addTag('property_info.description_extractor');
        $container->registerForAutoconfiguration(PropertyAccessExtractorInterface::class)
            ->addTag('property_info.access_extractor');
        $container->registerForAutoconfiguration(PropertyInitializableExtractorInterface::class)
            ->addTag('property_info.initializable_extractor');

        if (!$config['with_constructor_extractor']) {
            $container->removeDefinition('property_info.constructor_extractor');
        }

        if (
            ContainerBuilder::willBeAvailable('phpstan/phpdoc-parser', PhpDocParser::class, ['symfony/property-info'])
            && ContainerBuilder::willBeAvailable('phpdocumentor/type-resolver', ContextFactory::class, ['symfony/property-info'])
        ) {
            $container->register('property_info.phpstan_extractor', PhpStanExtractor::class)
                ->addTag('property_info.type_extractor', ['priority' => -1000])
                ->addTag('property_info.constructor_extractor', ['priority' => -1000]);
        }

        if (ContainerBuilder::willBeAvailable('phpdocumentor/reflection-docblock', DocBlockFactoryInterface::class, ['symfony/property-info'])) {
            $container->register('property_info.php_doc_extractor', PhpDocExtractor::class)
                ->addTag('property_info.description_extractor', ['priority' => -1000])
                ->addTag('property_info.type_extractor', ['priority' => -1001])
                ->addTag('property_info.constructor_extractor', ['priority' => -1001]);
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('property_info.cache');
        }
    }
}
