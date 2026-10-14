<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\ValidatorPropertySchemaEnricher;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\JsonSchema\SchemaGeneratorInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('json_schema.generator', SchemaGenerator::class)
            ->args([
                service('property_info'),
                service('json_schema.definition_policy'),
                tagged_iterator('json_schema.class_schema_resolver'),
                tagged_iterator('json_schema.property_schema_provider'),
                tagged_iterator('json_schema.property_schema_enricher'),
                tagged_iterator('json_schema.definition_processor'),
                service('serializer.name_converter.metadata_aware')->ignoreOnInvalid(),
                service('serializer.mapping.class_metadata_factory')->ignoreOnInvalid(),
                service('serializer.mapping.class_discriminator_resolver')->ignoreOnInvalid(),
                service('type_info.resolver')->ignoreOnInvalid(),
            ])
        ->alias(SchemaGeneratorInterface::class, 'json_schema.generator')

        ->set('json_schema.definition_policy', ShortNameDefinitionPolicy::class)

        // runs last, so that the resolvers of the app can describe dates, UIDs and enums their own way
        ->set('json_schema.class_schema_resolver.native', NativeClassSchemaResolver::class)
            ->args([
                [],
                service('serializer.mapping.class_metadata_factory')->ignoreOnInvalid(),
            ])
            ->tag('json_schema.class_schema_resolver', ['priority' => -1000])

        // runs first, so that the enrichers of the app see the keywords of the constraints
        ->set('json_schema.property_schema_enricher.validator', ValidatorPropertySchemaEnricher::class)
            ->args([service('validator.mapping.class_metadata_factory')])
            ->tag('json_schema.property_schema_enricher', ['priority' => 100])
            ->tag('container.remove_if_missing', ['service' => 'validator.mapping.class_metadata_factory'])

        // runs last, so that the keywords of #[JsonSchemaConstraint] win
        ->set('json_schema.property_schema_enricher.attribute', AttributePropertySchemaEnricher::class)
            ->tag('json_schema.property_schema_enricher', ['priority' => -100])
    ;
};
