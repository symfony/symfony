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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Enricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\JsonSchema\Schema;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\JsonSchema\Tests\Fixtures\AccountWithAccessors;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\AuthorSpotlight;
use Symfony\Component\JsonSchema\Tests\Fixtures\AuthorWithBiography;
use Symfony\Component\JsonSchema\Tests\Fixtures\BookWithAuthors;
use Symfony\Component\JsonSchema\Tests\Fixtures\CamelCaseProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\Catalog;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstructorInitializedShipment;
use Symfony\Component\JsonSchema\Tests\Fixtures\ContactWithFormatConstraints;
use Symfony\Component\JsonSchema\Tests\Fixtures\DocumentedArticle;
use Symfony\Component\JsonSchema\Tests\Fixtures\EnumCollectionDefaults;
use Symfony\Component\JsonSchema\Tests\Fixtures\FixedPropertySchemaProvider;
use Symfony\Component\JsonSchema\Tests\Fixtures\GroupedProduct;
use Symfony\Component\JsonSchema\Tests\Fixtures\IdentifierDefinitionProcessor;
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory;
use Symfony\Component\JsonSchema\Tests\Fixtures\IriReferenceClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\Library;
use Symfony\Component\JsonSchema\Tests\Fixtures\MarkingPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Tests\Fixtures\NativeObjectProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\NestedAttributesBook;
use Symfony\Component\JsonSchema\Tests\Fixtures\NonSerializableProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\NullableUnionChoice;
use Symfony\Component\JsonSchema\Tests\Fixtures\PrivatelyConstructedSnapshot;
use Symfony\Component\JsonSchema\Tests\Fixtures\ProductPair;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionProcessor;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingNameConverter;
use Symfony\Component\JsonSchema\Tests\Fixtures\ScalarProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\SelfReferencingCategory;
use Symfony\Component\JsonSchema\Tests\Fixtures\SequencedSignup;
use Symfony\Component\JsonSchema\Tests\Fixtures\SerializerMappedAccount;
use Symfony\Component\JsonSchema\Tests\Fixtures\Shelf;
use Symfony\Component\JsonSchema\Tests\Fixtures\StrictInput;
use Symfony\Component\JsonSchema\Tests\Fixtures\UnionProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\ValidatedRegistration;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints\GroupSequence;

class SchemaGeneratorTest extends TestCase
{
    #[DataProvider('provideFixtureClasses')]
    public function testGeneratesTheExpectedSchema(string $class)
    {
        $schema = SchemaGenerator::create()->generate(Type::object($class));

        $expectedFile = __DIR__.'/Fixtures/schema/'.str_replace('\\', '/', substr($class, \strlen('Symfony\\Component\\JsonSchema\\Tests\\Fixtures\\'))).'.schema.json';
        $json = json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION)."\n";

        if ($_ENV['TEST_GENERATE_FIXTURES'] ?? false) {
            if (!is_dir(\dirname($expectedFile))) {
                mkdir(\dirname($expectedFile), recursive: true);
            }
            file_put_contents($expectedFile, $json);
            $this->markTestIncomplete('TEST_GENERATE_FIXTURES is set');
        }

        $this->assertStringEqualsFile($expectedFile, $json);
    }

    public static function provideFixtureClasses(): iterable
    {
        foreach ([
            AccountWithAccessors::class,
            Author::class,
            AuthorSpotlight::class,
            AuthorWithBiography::class,
            BookWithAuthors::class,
            CamelCaseProperties::class,
            Catalog\Product::class,
            ConstructorInitializedShipment::class,
            ContactWithFormatConstraints::class,
            DocumentedArticle::class,
            EnumCollectionDefaults::class,
            GroupedProduct::class,
            Inventory\Product::class,
            Library::class,
            NativeObjectProperties::class,
            NestedAttributesBook::class,
            NonSerializableProperties::class,
            NullableUnionChoice::class,
            PrivatelyConstructedSnapshot::class,
            ProductPair::class,
            ScalarProperties::class,
            SelfReferencingCategory::class,
            SequencedSignup::class,
            SerializerMappedAccount::class,
            Shelf::class,
            StrictInput::class,
            UnionProperties::class,
            ValidatedRegistration::class,
        ] as $class) {
            yield $class => [$class];
        }
    }

    #[RequiresPhpExtension('gmp')]
    public function testGmpIsAString()
    {
        $this->assertSame(['type' => 'string'], SchemaGenerator::create()->generate(Type::object(\GMP::class))->getRoot());
    }

    public function testDifferentShapesNamedAlikeThrow()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Definition "Author-first_name"');

        SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(attributes: ['author' => ['first_name'], 'coAuthor' => ['first', 'name']]));
    }

    public function testSameShapeReachedTwiceSharesOneDefinition()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(attributes: ['author' => ['name'], 'coAuthor' => ['name']]));

        $this->assertSame(['BookWithAuthors-author.name_coAuthor.name', 'Author-name'], array_keys($schema->getDefinitions()));
    }

    public function testReferenceSiblingsAreWrappedInAllOfForOpenApi30()
    {
        $definition = SchemaGenerator::create()->generate(Type::object(AuthorSpotlight::class), new Configuration(Dialect::openApi30()))->getDefinitions()['AuthorSpotlight'];

        $this->assertSameSchema([
            'allOf' => [['$ref' => '#/components/schemas/Author']],
            'readOnly' => true,
            'description' => 'The highlighted author.',
        ], $definition['properties']['author']);
    }

    public function testReferenceSiblingsAreKeptForOpenApi31()
    {
        $definition = SchemaGenerator::create()->generate(Type::object(AuthorSpotlight::class), new Configuration(Dialect::openApi31()))->getDefinitions()['AuthorSpotlight'];

        $this->assertSameSchema([
            '$ref' => '#/components/schemas/Author',
            'readOnly' => true,
            'description' => 'The highlighted author.',
        ], $definition['properties']['author']);
    }

    public function testListRootTypeBecomesAnArrayOfReferences()
    {
        $schema = SchemaGenerator::create()->generate(Type::list(Type::object(Author::class)));

        $this->assertSameSchema(['type' => 'array', 'items' => ['$ref' => '#/$defs/Author']], $schema->getRoot());
        $this->assertSame(['Author'], array_keys($schema->getDefinitions()));
    }

    public function testBuiltinRootTypeHasNoDefinition()
    {
        $schema = SchemaGenerator::create()->generate(Type::nullable(Type::int()));

        $this->assertSameSchema(['type' => ['integer', 'null']], $schema->getRoot());
        $this->assertSame([], $schema->getDefinitions());
    }

    public function testArrayShapeBecomesAnInlineObject()
    {
        $schema = SchemaGenerator::create()->generate(Type::arrayShape(['name' => Type::string(), 'age' => ['type' => Type::int(), 'optional' => true]]));

        $this->assertSameSchema([
            'type' => 'object',
            'properties' => ['age' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            'required' => ['name'],
            'additionalProperties' => false,
        ], $schema->getRoot());
    }

    public function testListShapedArrayShapeBecomesAnArray()
    {
        $schema = SchemaGenerator::create()->generate(Type::arrayShape([Type::int(), ['type' => Type::string(), 'optional' => true]]));

        $this->assertSameSchema([
            'type' => 'array',
            'items' => ['anyOf' => [['type' => 'integer'], ['type' => 'string']]],
            'minItems' => 1,
            'maxItems' => 2,
        ], $schema->getRoot());
    }

    public function testUnsealedListShapedArrayShapeAcceptsExtraItems()
    {
        $schema = SchemaGenerator::create()->generate(Type::arrayShape([Type::int()], false, Type::int(), Type::bool()));

        $this->assertSameSchema([
            'type' => 'array',
            'items' => ['anyOf' => [['type' => 'boolean'], ['type' => 'integer']]],
            'minItems' => 1,
        ], $schema->getRoot());
    }

    public function testEmptyArrayShapeBecomesAnEmptyArray()
    {
        $schema = SchemaGenerator::create()->generate(Type::arrayShape([]));

        $this->assertSameSchema(['type' => 'array', 'items' => [], 'maxItems' => 0], $schema->getRoot());
    }

    public function testUnsupportedRootTypeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"callable"');

        SchemaGenerator::create()->generate(Type::builtin(TypeIdentifier::CALLABLE));
    }

    public function testFlattenProducesNoReferencesWhenAcyclic()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class))->flatten();

        $author = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];

        $this->assertSame([], $schema->getDefinitions());
        $this->assertStringNotContainsString('$ref', json_encode($schema));
        $this->assertSameSchema([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'author' => $author,
                'coAuthor' => ['anyOf' => [$author, ['type' => 'null']]],
                'reviewers' => ['type' => 'array', 'items' => $author],
                'authorsByRole' => ['type' => 'object', 'additionalProperties' => $author],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ], $schema->getRoot());
    }

    public function testFlattenKeepsTheCyclicDefinitions()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(SelfReferencingCategory::class))->flatten();

        $this->assertSameSchema([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'parent' => ['anyOf' => [['$ref' => '#/$defs/SelfReferencingCategory'], ['type' => 'null']]],
            ],
        ], $schema->getRoot());
        $this->assertSame(['SelfReferencingCategory'], array_keys($schema->getDefinitions()));
    }

    public function testOpenApi30UsesNullableFlag()
    {
        $config = new Configuration(dialect: Dialect::openApi30());

        $scalars = SchemaGenerator::create()->generate(Type::object(ScalarProperties::class), $config)->getDefinitions()['ScalarProperties']['properties'];
        $book = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), $config)->getDefinitions()['BookWithAuthors']['properties'];

        $this->assertSameSchema(['type' => 'string', 'nullable' => true], $scalars['nickname']);
        $this->assertSameSchema(['type' => 'boolean', 'enum' => [true], 'default' => true], $scalars['enabled']);
        $this->assertSameSchema(['allOf' => [['$ref' => '#/components/schemas/Author']], 'nullable' => true], $book['coAuthor']);
    }

    public function testSwagger20DropsNullability()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(dialect: Dialect::swagger20()));

        $this->assertSameSchema(['$ref' => '#/definitions/Author'], $schema->getDefinitions()['BookWithAuthors']['properties']['coAuthor']);
    }

    public function testIntersectionTypesBecomeAllOfAndKeepMemberDefinitions()
    {
        $schema = SchemaGenerator::create()->generate(Type::intersection(Type::object(Author::class), Type::object(SelfReferencingCategory::class)));

        $this->assertSameSchema(['allOf' => [['$ref' => '#/$defs/Author'], ['$ref' => '#/$defs/SelfReferencingCategory']]], $schema->getRoot());
        $this->assertSame(['Author', 'SelfReferencingCategory'], array_keys($schema->getDefinitions()));
    }

    public function testGroupsFilterPropertiesAndSuffixTheDefinitionName()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read']));

        $this->assertSameSchema(['$ref' => '#/$defs/GroupedProduct-product.read'], $schema->getRoot());
        $this->assertSame(['id', 'name'], array_keys($schema->getDefinitions()['GroupedProduct-product.read']['properties']));
    }

    public function testIgnoredAttributesAreRemoved()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read'], ignoredAttributes: ['name']));

        $this->assertSame(['id'], array_keys($schema->getDefinitions()['GroupedProduct-product.read']['properties']));
    }

    public function testAttributesAreNarrowedIntoNestedDefinitions()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(NestedAttributesBook::class), new Configuration(attributes: ['title', 'author' => ['name']]));

        $this->assertSameSchema(['$ref' => '#/$defs/NestedAttributesBook-title_author.name'], $schema->getRoot());
        $this->assertSame(['title', 'author'], array_keys($schema->getDefinitions()['NestedAttributesBook-title_author.name']['properties']));
        $this->assertSameSchema(['$ref' => '#/$defs/AuthorWithBiography-name'], $schema->getDefinitions()['NestedAttributesBook-title_author.name']['properties']['author']);
        $this->assertSame(['name'], array_keys($schema->getDefinitions()['AuthorWithBiography-name']['properties']));
    }

    public function testDefinitionPrefixAppliesToTheRootOnly()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(definitionPrefix: 'Book'));

        $this->assertSameSchema(['$ref' => '#/$defs/Book'], $schema->getRoot());
        $this->assertSame(['Book', 'Author'], array_keys($schema->getDefinitions()));
    }

    public function testDefinitionPrefixOnSelfReferencingClassKeepsTheNestedDefinitionSeparate()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(SelfReferencingCategory::class), new Configuration(definitionPrefix: 'Category'));

        $this->assertSameSchema(['$ref' => '#/$defs/Category'], $schema->getRoot());
        $this->assertSame(['Category', 'SelfReferencingCategory'], array_keys($schema->getDefinitions()));
        $this->assertSameSchema(
            ['anyOf' => [['$ref' => '#/$defs/SelfReferencingCategory'], ['type' => 'null']]],
            $schema->getDefinitions()['Category']['properties']['parent'],
        );
    }

    public function testDefinitionPolicyReceivesTheOwningPropertyOfNestedDefinitions()
    {
        $policy = new RecordingDefinitionPolicy();

        (new SchemaGenerator(self::createReflectionPropertyInfo(), $policy))->generate(Type::object(BookWithAuthors::class));

        $this->assertEquals([
            [BookWithAuthors::class, null],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'author', new Configuration())],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'coAuthor', new Configuration())],
        ], $policy->calls);
    }

    public function testDefinitionParentChainsUpToTheRoot()
    {
        $policy = new RecordingDefinitionPolicy();

        (new SchemaGenerator(self::createReflectionPropertyInfo(), $policy))->generate(Type::object(Library::class));

        [$authorClass, $authorParent] = $policy->calls[2];

        $this->assertSame(Author::class, $authorClass);
        $this->assertSame(Shelf::class, $authorParent->class);
        $this->assertSame('topAuthor', $authorParent->property);
        $this->assertSame(Library::class, $authorParent->parent->class);
        $this->assertSame('shelf', $authorParent->parent->property);
        $this->assertNull($authorParent->parent->parent);
    }

    public function testDefinitionProcessorsProcessRootAndNestedDefinitions()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), definitionProcessors: [new IdentifierDefinitionProcessor()]);

        $schema = $generator->generate(Type::object(Library::class));

        $this->assertSameSchema([
            'Library' => ['type' => 'object', 'properties' => ['@id' => ['type' => 'string'], 'shelf' => ['$ref' => '#/$defs/Shelf']]],
            'Shelf' => ['type' => 'object', 'properties' => ['@id' => ['type' => 'string'], 'topAuthor' => ['$ref' => '#/$defs/Author']]],
            'Author' => ['type' => 'object', 'properties' => ['@id' => ['type' => 'string'], 'name' => ['type' => 'string']]],
        ], $schema->getDefinitions());
    }

    public function testDefinitionProcessorsReceiveTheDefinitionParent()
    {
        $processor = new RecordingDefinitionProcessor();

        (new SchemaGenerator(self::createReflectionPropertyInfo(), definitionProcessors: [$processor]))->generate(Type::object(Library::class));

        $this->assertEquals([
            [Author::class, new DefinitionParent(Shelf::class, 'topAuthor', new Configuration(), new DefinitionParent(Library::class, 'shelf', new Configuration()))],
            [Shelf::class, new DefinitionParent(Library::class, 'shelf', new Configuration())],
            [Library::class, null],
        ], $processor->calls);
    }

    public function testDefinitionProcessorsRunBeforeFlatteningInlinesTheDefinitions()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), definitionProcessors: [new IdentifierDefinitionProcessor()]);

        $schema = $generator->generate(Type::object(Shelf::class))->flatten();

        $this->assertSameSchema([
            'type' => 'object',
            'properties' => [
                '@id' => ['type' => 'string'],
                'topAuthor' => ['type' => 'object', 'properties' => ['@id' => ['type' => 'string'], 'name' => ['type' => 'string']]],
            ],
        ], $schema->getRoot());
    }

    public function testDefinitionProcessorsRunInRegistrationOrder()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), definitionProcessors: [new RecordingDefinitionProcessor('first'), new RecordingDefinitionProcessor('second')]);

        $definition = $generator->generate(Type::object(Author::class))->getDefinitions()['Author'];

        $this->assertSame(['first', 'second'], $definition['x-processors']);
    }

    public function testDefinitionProcessorsDoNotRunOnResolvedClasses()
    {
        $processor = new RecordingDefinitionProcessor();

        (new SchemaGenerator(self::createReflectionPropertyInfo(), classSchemaResolvers: [new NativeClassSchemaResolver()], definitionProcessors: [$processor]))->generate(Type::object(\DateTimeImmutable::class));

        $this->assertSame([], $processor->calls);
    }

    public function testClassSchemaResolversReceiveTheDefinitionParent()
    {
        $resolver = new RecordingClassSchemaResolver();

        (new SchemaGenerator(self::createPhpDocPropertyInfo(), classSchemaResolvers: [$resolver]))->generate(Type::object(BookWithAuthors::class));

        $this->assertEquals([
            [BookWithAuthors::class, null],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'author', new Configuration())],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'coAuthor', new Configuration())],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'reviewers', new Configuration())],
            [Author::class, new DefinitionParent(BookWithAuthors::class, 'authorsByRole', new Configuration())],
        ], $resolver->calls);
    }

    public function testClassSchemaResolversReceiveTheOwnerConfigurationOnTheDefinitionParent()
    {
        $resolver = new RecordingClassSchemaResolver();
        $config = new Configuration(attributes: ['title', 'author' => ['name']]);

        (new SchemaGenerator(self::createPhpDocPropertyInfo(), classSchemaResolvers: [$resolver]))->generate(Type::object(BookWithAuthors::class), $config);

        [$authorClass, $authorParent] = $resolver->calls[1];

        $this->assertSame(Author::class, $authorClass);
        $this->assertSame(['name'], $resolver->configs[1]->attributes);
        $this->assertSame($config, $authorParent->config);
    }

    public function testDefinitionParentChainCarriesTheRootConfiguration()
    {
        $resolver = new RecordingClassSchemaResolver();
        $config = new Configuration(attributes: ['shelf' => ['topAuthor' => ['name']]]);

        (new SchemaGenerator(self::createReflectionPropertyInfo(), classSchemaResolvers: [$resolver]))->generate(Type::object(Library::class), $config);

        [$authorClass, $authorParent] = $resolver->calls[2];

        $this->assertSame(Author::class, $authorClass);
        $this->assertSame(['name'], $resolver->configs[2]->attributes);
        $this->assertSame(['topAuthor' => ['name']], $authorParent->config->attributes);
        $this->assertSame($config, $authorParent->parent->config);
    }

    public function testClassSchemaResolverCanReplaceNestedRelationsByAnIriReferenceWithoutDefinition()
    {
        $generator = new SchemaGenerator(self::createPhpDocPropertyInfo(), classSchemaResolvers: [new IriReferenceClassSchemaResolver(Author::class)]);

        $schema = $generator->generate(Type::object(BookWithAuthors::class));

        $this->assertSame(['BookWithAuthors'], array_keys($schema->getDefinitions()));
        $this->assertSameSchema(['type' => ['string', 'null'], 'format' => 'iri-reference'], $schema->getDefinitions()['BookWithAuthors']['properties']['coAuthor']);
        $this->assertSameSchema(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'iri-reference']], $schema->getDefinitions()['BookWithAuthors']['properties']['reviewers']);
    }

    public function testPropertySchemaProviderReplacesTheGeneratedSchema()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), propertySchemaProviders: [new FixedPropertySchemaProvider(BookWithAuthors::class, 'coAuthor', ['type' => 'string', 'format' => 'iri-reference'])]);

        $definition = $generator->generate(Type::object(BookWithAuthors::class))->getDefinitions()['BookWithAuthors'];

        $this->assertSameSchema(['type' => 'string', 'format' => 'iri-reference'], $definition['properties']['coAuthor']);
    }

    public function testRelatedClassReferencedOnlyByAProvidedPropertyHasNoDefinition()
    {
        $resolver = new RecordingClassSchemaResolver();
        $generator = new SchemaGenerator(
            self::createReflectionPropertyInfo(),
            classSchemaResolvers: [$resolver],
            propertySchemaProviders: [new FixedPropertySchemaProvider(Library::class, 'shelf', ['type' => 'string'])],
        );

        $schema = $generator->generate(Type::object(Library::class));

        $this->assertSame(['Library'], array_keys($schema->getDefinitions()));
        $this->assertEquals([[Library::class, null]], $resolver->calls);
    }

    public function testEnrichersRunOnTheProvidedPropertySchema()
    {
        $generator = new SchemaGenerator(
            self::createReflectionPropertyInfo(),
            propertySchemaProviders: [new FixedPropertySchemaProvider(Library::class, 'shelf', ['type' => 'string'])],
            propertySchemaEnrichers: [new MarkingPropertySchemaEnricher()],
        );

        $definition = $generator->generate(Type::object(Library::class))->getDefinitions()['Library'];

        $this->assertSameSchema(['type' => 'string', 'x-enriched' => true], $definition['properties']['shelf']);
    }

    public function testFirstPropertySchemaProviderReturningASchemaWins()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), propertySchemaProviders: [
            new FixedPropertySchemaProvider(Library::class, 'unknown', ['type' => 'boolean']),
            new FixedPropertySchemaProvider(Library::class, 'shelf', ['type' => 'string']),
            new FixedPropertySchemaProvider(Library::class, 'shelf', ['type' => 'integer']),
        ]);

        $definition = $generator->generate(Type::object(Library::class))->getDefinitions()['Library'];

        $this->assertSameSchema(['type' => 'string'], $definition['properties']['shelf']);
    }

    public function testDefinitionNameReplacesTheGroupsSuffix()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read'], definitionName: 'Custom'));

        $this->assertSameSchema(['$ref' => '#/$defs/GroupedProduct-Custom'], $schema->getRoot());
    }

    public function testDisallowingExtraAttributesClosesTheObject()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(StrictInput::class), new Configuration(allowExtraAttributes: false));

        $this->assertFalse($schema->getDefinitions()['StrictInput']['additionalProperties']);
    }

    public function testListedPropertyWithoutAnyAccessIsKeptWithoutFlags()
    {
        $properties = SchemaGenerator::create()->generate(Type::object(AccountWithAccessors::class), new Configuration(groups: ['account:internal']))->getDefinitions()['AccountWithAccessors-account.internal']['properties'];

        $this->assertSameSchema([
            'email' => ['type' => 'string'],
            'auditTrail' => ['type' => 'string'],
        ], $properties);
    }

    public function testValidationGroupsSelectConstraintsAndSuffixTheDefinitionName()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(ValidatedRegistration::class), new Configuration(validationGroups: ['registration:create']));
        $definition = $schema->getDefinitions()['ValidatedRegistration-validation.registration.create'];

        $this->assertSameSchema(['type' => ['string', 'null'], 'maxLength' => 10], $definition['properties']['invitationCode']);
        $this->assertSameSchema(['type' => 'string', 'default' => ''], $definition['properties']['username']);
        $this->assertSame(['invitationCode'], $definition['required']);
    }

    public function testGroupSequenceValidationGroupsAreFlattened()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(ValidatedRegistration::class), new Configuration(validationGroups: new GroupSequence(['registration:create'])));

        $this->assertSame(['invitationCode'], $schema->getDefinitions()['ValidatedRegistration-validation.registration.create']['required']);
    }

    public function testJsonSchemaConstraintAttributeFollowsTheDialect()
    {
        $properties = SchemaGenerator::create()->generate(Type::object(DocumentedArticle::class), new Configuration(dialect: Dialect::openApi30()))->getDefinitions()['DocumentedArticle']['properties'];

        $this->assertSameSchema(['type' => 'integer', 'minimum' => 0, 'exclusiveMinimum' => true], $properties['views']);
        $this->assertSameSchema(['type' => 'string', 'enum' => ['article']], $properties['kind']);
        $this->assertSame('hello-world', $properties['slug']['example']);
        $this->assertArrayNotHasKey('examples', $properties['slug']);
    }

    public function testShortDescriptionIsEmittedWithoutAnyEnricher()
    {
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor], [new PhpDocExtractor()], [$reflectionExtractor], [$reflectionExtractor]);

        $properties = (new SchemaGenerator($propertyInfo))->generate(Type::object(DocumentedArticle::class))->getDefinitions()['DocumentedArticle']['properties'];

        $this->assertSame('The article headline.', $properties['headline']['description']);
        $this->assertArrayNotHasKey('description', $properties['slug']);
    }

    public function testNameConverterRenamesProperties()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), new ShortNameDefinitionPolicy(), propertySchemaEnrichers: [new AttributePropertySchemaEnricher()], nameConverter: new CamelCaseToSnakeCaseNameConverter());

        $definition = $generator->generate(Type::object(CamelCaseProperties::class))->getDefinitions()['CamelCaseProperties'];

        $this->assertSameSchema(['type' => 'object', 'properties' => ['first_name' => ['type' => 'string']], 'required' => ['first_name']], $definition);
    }

    public function testSerializerFormatIsForwardedToTheNameConverter()
    {
        $nameConverter = new RecordingNameConverter();

        (new SchemaGenerator(self::createReflectionPropertyInfo(), nameConverter: $nameConverter))->generate(Type::object(CamelCaseProperties::class), new Configuration(groups: ['read'], format: 'jsonld'));

        $this->assertSame([['firstName', CamelCaseProperties::class, 'jsonld', ['groups' => ['read']]]], $nameConverter->calls);
    }

    private static function createReflectionPropertyInfo(): PropertyInfoExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();

        return new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor], [], [$reflectionExtractor], [$reflectionExtractor]);
    }

    private static function createPhpDocPropertyInfo(): PropertyInfoExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();

        return new PropertyInfoExtractor([$reflectionExtractor], [new PhpStanExtractor(), $reflectionExtractor], [], [$reflectionExtractor], [$reflectionExtractor]);
    }

    private function assertSameSchema(array $expected, array|Schema $actual): void
    {
        $this->assertSame(
            json_encode($expected, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
            json_encode($actual, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        );
    }
}
