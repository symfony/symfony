<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\DependencyInjection\SerializerDefaultContextPass;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\PropertySchemaProvider\PropertySchemaProviderInterface;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\TypeInfo\TypeInfoBundle;

/**
 * Provides the JSON Schema generator.
 *
 * @experimental
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(PropertyInfoBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(TypeInfoBundle::class, ignoreOnInvalid: true)]
class JsonSchemaBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        // before SerializerPass, which removes the "serializer.default_context" parameter
        $container->addCompilerPass(new SerializerDefaultContextPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/json_schema.php');

        $container->registerForAutoconfiguration(ClassSchemaResolverInterface::class)
            ->addTag('json_schema.class_schema_resolver');
        $container->registerForAutoconfiguration(PropertySchemaProviderInterface::class)
            ->addTag('json_schema.property_schema_provider');
        $container->registerForAutoconfiguration(PropertySchemaEnricherInterface::class)
            ->addTag('json_schema.property_schema_enricher');
        $container->registerForAutoconfiguration(DefinitionProcessorInterface::class)
            ->addTag('json_schema.definition_processor');
    }
}
