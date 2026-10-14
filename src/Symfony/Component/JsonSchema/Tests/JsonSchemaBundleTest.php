<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\JsonSchemaBundle;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGeneratorInterface;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\BookWithAuthors;
use Symfony\Component\JsonSchema\Tests\Fixtures\Drawing;
use Symfony\Component\JsonSchema\Tests\Fixtures\FixedPropertySchemaProvider;
use Symfony\Component\JsonSchema\Tests\Fixtures\IdentifierDefinitionProcessor;
use Symfony\Component\JsonSchema\Tests\Fixtures\IriReferenceClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\JsonSchemaBundleTestAppBundle;
use Symfony\Component\JsonSchema\Tests\Fixtures\JsonSchemaBundleTestKernel;
use Symfony\Component\JsonSchema\Tests\Fixtures\MarkingPropertySchemaEnricher;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\SerializerBundle;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ValidationBundle;

class JsonSchemaBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_json_schema_bundle_test';

        if (!class_exists(AbstractKernel::class) || !class_exists(PropertyInfoBundle::class)) {
            $this->markTestSkipped('The bundle needs symfony/dependency-injection and symfony/property-info 8.2 or higher.');
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testGeneratorIsWiredToTheTaggedExtensions()
    {
        $container = new ContainerBuilder();
        $bundle = new JsonSchemaBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load([[]], $container);

        $this->assertSame('json_schema.generator', (string) $container->getAlias(SchemaGeneratorInterface::class));

        $definition = $container->getDefinition('json_schema.generator');

        foreach ([2 => 'json_schema.class_schema_resolver', 3 => 'json_schema.property_schema_provider', 4 => 'json_schema.property_schema_enricher', 5 => 'json_schema.definition_processor'] as $index => $tag) {
            $this->assertInstanceOf(TaggedIteratorArgument::class, $definition->getArgument($index));
            $this->assertSame($tag, $definition->getArgument($index)->getTag());
        }
    }

    public function testGeneratorDescribesClasses()
    {
        $generator = $this->bootKernel('describe')->getContainer()->get('test.json_schema.generator');

        $this->assertSame(['type' => 'object', 'properties' => ['name' => ['type' => 'string']]], $generator->generate(Type::object(Author::class))->getDefinitions()['Author']);
    }

    public function testExtensionsAreAutoconfigured()
    {
        $generator = $this->bootKernel('autoconfigure', build: static function (ContainerBuilder $container) {
            $container->register('app.iri_resolver', IriReferenceClassSchemaResolver::class)->setArguments([Author::class])->setAutoconfigured(true);
            $container->register('app.isbn_provider', FixedPropertySchemaProvider::class)->setArguments([BookWithAuthors::class, 'title', ['type' => 'string', 'format' => 'isbn']])->setAutoconfigured(true);
            $container->register('app.marking_enricher', MarkingPropertySchemaEnricher::class)->setAutoconfigured(true);
            $container->register('app.identifier_processor', IdentifierDefinitionProcessor::class)->setAutoconfigured(true);
        })->getContainer()->get('test.json_schema.generator');

        $properties = $generator->generate(Type::object(BookWithAuthors::class))->getDefinitions()['BookWithAuthors']['properties'];

        $this->assertSame(['type' => 'string', 'format' => 'isbn', 'x-enriched' => true], $properties['title']);
        $this->assertSame(['type' => 'string', 'format' => 'iri-reference', 'x-enriched' => true], $properties['author']);
        $this->assertSame(['type' => 'string'], $properties['@id']);
    }

    public function testBuiltInExtensionsRunAroundTheOnesOfOtherBundles()
    {
        $generator = $this->bootKernel('order', [new JsonSchemaBundleTestAppBundle(static function (ContainerBuilder $container) {
            $container->register('app.date_resolver', IriReferenceClassSchemaResolver::class)->setArguments([\DateTimeImmutable::class])->setAutoconfigured(true);
            $container->register('app.describing_enricher', JsonSchemaBundleTestDescribingEnricher::class)->setAutoconfigured(true);
        })])->getContainer()->get('test.json_schema.generator');

        $properties = $generator->generate(Type::object(JsonSchemaBundleTestEvent::class))->getDefinitions()['JsonSchemaBundleTestEvent']['properties'];

        $this->assertSame(['type' => 'string', 'format' => 'iri-reference', 'description' => 'From the app.'], $properties['startsAt']);
        $this->assertSame(['type' => 'string', 'description' => 'From the attribute.'], $properties['title']);
    }

    public function testValidatorConstraintsAreIgnoredWithoutTheValidator()
    {
        $generator = $this->bootKernel('no_validator')->getContainer()->get('test.json_schema.generator');

        $this->assertSame(['type' => 'string'], $generator->generate(Type::object(JsonSchemaBundleTestCoupon::class))->getDefinitions()['JsonSchemaBundleTestCoupon']['properties']['code']);
    }

    public function testValidatorConstraintsAreDescribed()
    {
        if (!class_exists(ValidationBundle::class)) {
            $this->markTestSkipped('The validator bundle needs symfony/validator 8.2 or higher.');
        }

        $generator = $this->bootKernel('validator', [new ValidationBundle()], build: static function (ContainerBuilder $container) {
            // the validator bundle expects parameters that FrameworkBundle defines
            $container->setParameter('kernel.charset', 'UTF-8');
            $container->setParameter('kernel.enabled_locales', []);
            $container->register('app.max_length_enricher', JsonSchemaBundleTestMaxLengthEnricher::class)->setAutoconfigured(true);
        })->getContainer()->get('test.json_schema.generator');

        $this->assertSame(['type' => 'string', 'maxLength' => 10, 'description' => 'At most 10 characters.'], $generator->generate(Type::object(JsonSchemaBundleTestCoupon::class))->getDefinitions()['JsonSchemaBundleTestCoupon']['properties']['code']);
    }

    public function testSerializerMappingAndDefaultContextAreFollowed()
    {
        if (!class_exists(SerializerBundle::class)) {
            $this->markTestSkipped('The serializer bundle needs symfony/serializer 8.2 or higher.');
        }

        $generator = $this->bootKernel('serializer', [new SerializerBundle()], ['serializer' => ['default_context' => ['datetime_format' => 'Y-m-d']]])->getContainer()->get('test.json_schema.generator');

        $this->assertSame(['published_on' => ['type' => 'string', 'format' => 'date']], $generator->generate(Type::object(JsonSchemaBundleTestArticle::class))->getDefinitions()['JsonSchemaBundleTestArticle']['properties']);
        $this->assertArrayHasKey('anyOf', $generator->generate(Type::object(Drawing::class))->getDefinitions()['Drawing']['properties']['main']);
    }

    /**
     * @param list<BundleInterface>               $bundles
     * @param array<string, array<string, mixed>> $extensionConfigs
     */
    private function bootKernel(string $environment, array $bundles = [], array $extensionConfigs = [], ?\Closure $build = null): JsonSchemaBundleTestKernel
    {
        $kernel = new JsonSchemaBundleTestKernel($environment, $this->varDir, $bundles, $extensionConfigs, $build);
        $kernel->boot();

        return $kernel;
    }
}

final class JsonSchemaBundleTestDescribingEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        return $property->withSchema([...$property->schema, 'description' => 'From the app.']);
    }
}

final class JsonSchemaBundleTestMaxLengthEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        return isset($property->schema['maxLength']) ? $property->withSchema([...$property->schema, 'description' => \sprintf('At most %d characters.', $property->schema['maxLength'])]) : $property;
    }
}

final class JsonSchemaBundleTestEvent
{
    public \DateTimeImmutable $startsAt;

    #[JsonSchemaConstraint(description: 'From the attribute.')]
    public string $title;
}

final class JsonSchemaBundleTestCoupon
{
    #[Assert\Length(max: 10)]
    public string $code;
}

final class JsonSchemaBundleTestArticle
{
    #[SerializedName('published_on')]
    public \DateTimeImmutable $publishedOn;
}
