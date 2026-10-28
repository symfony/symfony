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

use PHPStan\PhpDocParser\ParserConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\JsonSchema\NullSyntax;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\Schema;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\JsonSchema\Tests\Fixtures\AccountWithAccessors;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\AuthorSpotlight;
use Symfony\Component\JsonSchema\Tests\Fixtures\AuthorWithBiography;
use Symfony\Component\JsonSchema\Tests\Fixtures\BookWithAuthors;
use Symfony\Component\JsonSchema\Tests\Fixtures\CamelCaseProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\Catalog;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstantNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstrainedOrder;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstructorInitializedShipment;
use Symfony\Component\JsonSchema\Tests\Fixtures\ContactWithFormatConstraints;
use Symfony\Component\JsonSchema\Tests\Fixtures\DatedEvent;
use Symfony\Component\JsonSchema\Tests\Fixtures\DocumentedArticle;
use Symfony\Component\JsonSchema\Tests\Fixtures\Drawing;
use Symfony\Component\JsonSchema\Tests\Fixtures\EnumCollectionDefaults;
use Symfony\Component\JsonSchema\Tests\Fixtures\FixedPropertySchemaProvider;
use Symfony\Component\JsonSchema\Tests\Fixtures\FixedSchemaPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Tests\Fixtures\GroupedProduct;
use Symfony\Component\JsonSchema\Tests\Fixtures\IdentifierDefinitionProcessor;
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory;
use Symfony\Component\JsonSchema\Tests\Fixtures\IriReferenceClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\Library;
use Symfony\Component\JsonSchema\Tests\Fixtures\ListOfClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\MarkingPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Tests\Fixtures\NativeObjectProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\NestedAttributesBook;
use Symfony\Component\JsonSchema\Tests\Fixtures\NonSerializableProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\NullableUnionChoice;
use Symfony\Component\JsonSchema\Tests\Fixtures\PrivatelyConstructedSnapshot;
use Symfony\Component\JsonSchema\Tests\Fixtures\ProductPair;
use Symfony\Component\JsonSchema\Tests\Fixtures\PublishedArticle;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionProcessor;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingNameConverter;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingPropertyListExtractor;
use Symfony\Component\JsonSchema\Tests\Fixtures\ScalarProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\SearchTool;
use Symfony\Component\JsonSchema\Tests\Fixtures\SelfReferencingCategory;
use Symfony\Component\JsonSchema\Tests\Fixtures\SequencedSignup;
use Symfony\Component\JsonSchema\Tests\Fixtures\SerializerMappedAccount;
use Symfony\Component\JsonSchema\Tests\Fixtures\ServiceDefaults;
use Symfony\Component\JsonSchema\Tests\Fixtures\Shape;
use Symfony\Component\JsonSchema\Tests\Fixtures\Shelf;
use Symfony\Component\JsonSchema\Tests\Fixtures\StrictInput;
use Symfony\Component\JsonSchema\Tests\Fixtures\Ticket;
use Symfony\Component\JsonSchema\Tests\Fixtures\UnionProperties;
use Symfony\Component\JsonSchema\Tests\Fixtures\ValidatedRegistration;
use Symfony\Component\JsonSchema\Tests\Fixtures\Vehicle;
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
            ConstrainedOrder::class,
            ConstructorInitializedShipment::class,
            ContactWithFormatConstraints::class,
            DocumentedArticle::class,
            Drawing::class,
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
            PublishedArticle::class,
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

    #[RequiresPhp('>=8.4.0')]
    #[RequiresPhpExtension('bcmath')]
    public function testBcMathNumberIsAString()
    {
        $this->assertSame(['type' => 'string'], SchemaGenerator::create()->generate(Type::object(\BcMath\Number::class))->getRoot());
    }

    public function testDifferentShapesNamedAlikeThrow()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Definition "Shape" already describes class "'.BookWithAuthors::class.'" with a different configuration, it cannot also describe class "'.Author::class.'", "'.ConstantNameDefinitionPolicy::class.'" must return distinct names for distinct shapes.');

        (new SchemaGenerator(self::createReflectionPropertyInfo(), new ConstantNameDefinitionPolicy('Shape')))->generate(Type::object(BookWithAuthors::class));
    }

    public function testSameClassWithDifferentIgnoredAttributesDoesNotClash()
    {
        $generator = SchemaGenerator::create();

        $narrowed = $generator->generate(Type::object(Author::class), new Configuration(ignoredAttributes: ['name']));
        $full = $generator->generate(Type::object(Author::class));

        $this->assertSame(['Author-ignored.name'], array_keys($narrowed->getDefinitions()));
        $this->assertSame(['Author'], array_keys($full->getDefinitions()));
        $this->assertArrayNotHasKey('name', $narrowed->getDefinitions()['Author-ignored.name']['properties'] ?? []);
        $this->assertArrayHasKey('name', $full->getDefinitions()['Author']['properties']);
    }

    public function testDocumentCollectsTheDefinitionsOfEveryCall()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();

        $book = $generator->generate(Type::object(BookWithAuthors::class), new Configuration(), $document);
        $category = $generator->generate(Type::object(SelfReferencingCategory::class), new Configuration(), $document);

        $this->assertSame(['BookWithAuthors', 'Author'], array_keys($book->getDefinitions()));
        $this->assertSame(['SelfReferencingCategory'], array_keys($category->getDefinitions()));
        $this->assertSame(['BookWithAuthors', 'Author', 'SelfReferencingCategory'], array_keys($document->getDefinitions()));
    }

    public function testGeneratorKeepsNoStateBetweenDocuments()
    {
        $generator = SchemaGenerator::create();

        $generator->generate(Type::object(Catalog\Product::class));
        $this->assertSameSchema(['$ref' => '#/$defs/Product'], $generator->generate(Type::object(Inventory\Product::class))->getRoot());

        $document = new SchemaDocument();
        $generator->generate(Type::object(Catalog\Product::class), new Configuration(), $document);
        $this->assertSameSchema(['$ref' => '#/$defs/Inventory.Product'], $generator->generate(Type::object(Inventory\Product::class), new Configuration(), $document)->getRoot());
    }

    public function testDifferentShapesNamedAlikeThrowAcrossTheCallsOfADocument()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $generator->generate(Type::object(Author::class), new Configuration(attributes: ['first_name']), $document);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Definition "Author-first_name" already describes class "'.Author::class.'" with a different configuration');

        $generator->generate(Type::object(Author::class), new Configuration(attributes: ['first', 'name']), $document);
    }

    public function testShapesNamedAlikeDoNotClashInSeparateDocuments()
    {
        $generator = SchemaGenerator::create();
        $generator->generate(Type::object(Author::class), new Configuration(attributes: ['first_name']));

        $schema = $generator->generate(Type::object(Author::class), new Configuration(attributes: ['first', 'name']));

        $this->assertSame(['Author-first_name'], array_keys($schema->getDefinitions()));
    }

    public function testDocumentRejectsAnotherReferencePath()
    {
        $document = new SchemaDocument();
        SchemaGenerator::create()->generate(Type::object(Author::class), new Configuration(), $document);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The definitions of this document are referenced as "#/$defs/", they cannot also be referenced as "#/components/schemas/".');

        SchemaGenerator::create()->generate(Type::object(Author::class), new Configuration(Dialect::openApi31()), $document);
    }

    public function testExtensionsDescribeOtherTypesInTheDocument()
    {
        $generator = new SchemaGenerator(self::createReflectionPropertyInfo(), new ShortNameDefinitionPolicy(), [new ListOfClassSchemaResolver(Shelf::class, Author::class)]);

        $schema = $generator->generate(Type::object(Library::class));

        $this->assertSame(['Library', 'Author'], array_keys($schema->getDefinitions()));
        $this->assertSameSchema(['type' => 'array', 'items' => ['$ref' => '#/$defs/Author']], $schema->getDefinitions()['Library']['properties']['shelf']);
    }

    public function testDocumentDescribesTypesOnlyWhileASchemaIsGenerated()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Types can only be described while a schema is generated in this document.');

        (new SchemaDocument())->describe(Type::int(), new Configuration());
    }

    public function testCreateAddsTheGivenExtensions()
    {
        $generator = SchemaGenerator::create(new ConstantNameDefinitionPolicy('Shape'), [new IriReferenceClassSchemaResolver(Author::class)], [new FixedPropertySchemaProvider(BookWithAuthors::class, 'title', ['type' => 'string', 'format' => 'isbn'])], [new MarkingPropertySchemaEnricher()], [new RecordingDefinitionProcessor('custom')]);

        $schema = $generator->generate(Type::object(BookWithAuthors::class));
        $definition = $schema->getDefinitions()['Shape'];

        $this->assertSameSchema(['$ref' => '#/$defs/Shape'], $schema->getRoot());
        $this->assertSameSchema(['type' => 'string', 'format' => 'isbn', 'x-enriched' => true], $definition['properties']['title']);
        $this->assertSameSchema(['type' => 'string', 'format' => 'iri-reference', 'x-enriched' => true], $definition['properties']['author']);
        $this->assertSame(['custom'], $definition['x-processors']);
    }

    public function testCreateRunsTheGivenEnrichersAfterTheBuiltInOnes()
    {
        $generator = SchemaGenerator::create(null, [], [], [new FixedSchemaPropertySchemaEnricher(['type' => 'string'])]);

        $definition = $generator->generate(Type::object(DocumentedArticle::class))->getDefinitions()['DocumentedArticle'];

        $this->assertSameSchema(['type' => 'string'], $definition['properties']['slug']);
    }

    public function testCreateRunsTheGivenResolversBeforeTheBuiltInOnes()
    {
        $generator = SchemaGenerator::create(null, [new IriReferenceClassSchemaResolver(\DateTimeImmutable::class)]);

        $properties = $generator->generate(Type::object(NativeObjectProperties::class))->getDefinitions()['NativeObjectProperties']['properties'];

        $this->assertSameSchema(['type' => 'string', 'format' => 'iri-reference'], $properties['createdAt']);
    }

    public function testBothDirectionsAreDescribedByDefault()
    {
        $definition = SchemaGenerator::create()->generate(Type::object(Ticket::class))->getDefinitions()['Ticket'];

        $this->assertEqualsCanonicalizing(['id', 'title', 'note', 'secret', 'status'], array_keys($definition['properties']));
        $this->assertTrue($definition['properties']['status']['readOnly']);
        $this->assertTrue($definition['properties']['secret']['writeOnly']);
        $this->assertSame(['id'], $definition['required']);
    }

    public function testInputDirectionDescribesTheWritableProperties()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Ticket::class), new Configuration(direction: Direction::Input));
        $definition = $schema->getDefinitions()['Ticket-input'];

        $this->assertEqualsCanonicalizing(['id', 'title', 'note', 'secret'], array_keys($definition['properties']));
        $this->assertArrayNotHasKey('writeOnly', $definition['properties']['secret']);
        $this->assertSame(['id'], $definition['required']);
    }

    public function testOutputDirectionDescribesTheReadablePropertiesAndRequiresTheInitializedOnes()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Ticket::class), new Configuration(direction: Direction::Output));
        $definition = $schema->getDefinitions()['Ticket-output'];

        $this->assertEqualsCanonicalizing(['id', 'title', 'note', 'status'], array_keys($definition['properties']));
        $this->assertArrayNotHasKey('readOnly', $definition['properties']['status']);
        $this->assertEqualsCanonicalizing(['id', 'title', 'status'], $definition['required']);
    }

    public function testOutputDirectionRequiresTheTypePropertyOfADiscriminatorMap()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Vehicle::class), new Configuration(direction: Direction::Output));

        $this->assertSameSchema(['$ref' => '#/$defs/Car-output', 'required' => ['type']], $schema->getRoot()['anyOf'][0]);
        $this->assertContains('type', $schema->getDefinitions()['Car-output']['required']);
    }

    public function testSerializerContextOfAPropertySelectsTheDateFormat()
    {
        $generator = SchemaGenerator::create();

        $properties = $generator->generate(Type::object(DatedEvent::class))->getDefinitions()['DatedEvent']['properties'];
        $this->assertSameSchema(['type' => 'string', 'format' => 'date'], $properties['day']);
        $this->assertSameSchema(['type' => 'string', 'format' => 'date'], $properties['deadline']);
        $this->assertSameSchema(['type' => 'string', 'format' => 'date-time'], $properties['createdAt']);

        $properties = $generator->generate(Type::object(DatedEvent::class), new Configuration(direction: Direction::Input))->getDefinitions()['DatedEvent-input']['properties'];
        $this->assertSameSchema(['type' => 'string'], $properties['deadline']);
    }

    public function testParametersBecomeTheProperties()
    {
        $schema = SchemaGenerator::create()->generateForParameters((new \ReflectionMethod(SearchTool::class, '__invoke'))->getParameters());

        $expected = [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'The text to search for'],
                'author' => ['anyOf' => [['$ref' => '#/$defs/Author'], ['type' => 'null']]],
                'limit' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => [], 'description' => 'Only the products with these tags'],
                'suit' => ['type' => 'string', 'enum' => ['hearts', 'spades'], 'default' => 'hearts'],
                'extra' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['query', 'author'],
        ];

        // descriptions are read with phpstan/phpdoc-parser 2
        if (!class_exists(ParserConfig::class)) {
            unset($expected['properties']['query']['description'], $expected['properties']['tags']['description']);
        }

        $this->assertSameSchema($expected, $schema->getRoot());
        $this->assertSame(['Author'], array_keys($schema->getDefinitions()));
    }

    public function testParametersCanBeClosed()
    {
        $schema = SchemaGenerator::create()->generateForParameters([], new Configuration(allowExtraAttributes: false));

        $this->assertSameSchema(['type' => 'object', 'additionalProperties' => false], $schema->getRoot());
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

        $this->assertSameSchema(['type' => 'array', 'items' => new \ArrayObject(), 'maxItems' => 0], $schema->getRoot());
    }

    public function testClassThatDoesNotExistIsAnObject()
    {
        $schema = SchemaGenerator::create()->generate(Type::object('Symfony\\Component\\JsonSchema\\Tests\\Fixtures\\NotInstalled'));

        $this->assertSameSchema(['NotInstalled' => ['type' => 'object']], $schema->getDefinitions());
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
                'reviewers' => ['type' => 'array', 'items' => $author, 'default' => []],
                'authorsByRole' => ['type' => 'object', 'additionalProperties' => $author, 'default' => new \ArrayObject()],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => []],
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

    public function testUnsupportedNullSyntaxDropsNullability()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(new Dialect('#/definitions/', NullSyntax::Unsupported, false, false, false, false)));

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

    public function testGroupsInAnotherOrderShareTheDefinition()
    {
        $generator = SchemaGenerator::create();

        $first = $generator->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:write', 'product:read'], ignoredAttributes: ['secret', 'id']));
        $second = $generator->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read', 'product:write'], ignoredAttributes: ['id', 'secret']));

        $this->assertSame(['GroupedProduct-product.read_product.write_ignored.id_secret'], array_keys($first->getDefinitions()));
        $this->assertSame(['GroupedProduct-product.read_product.write_ignored.id_secret'], array_keys($second->getDefinitions()));
    }

    public function testIgnoredAttributesAreRemoved()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read'], ignoredAttributes: ['name']));

        $this->assertSame(['id'], array_keys($schema->getDefinitions()['GroupedProduct-product.read_ignored.name']['properties']));
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

    public function testContextIsKeptInNestedConfigurations()
    {
        $resolver = new RecordingClassSchemaResolver();
        $config = new Configuration(attributes: ['shelf' => ['topAuthor']], context: ['operation' => 'get_library']);

        (new SchemaGenerator(self::createReflectionPropertyInfo(), new ShortNameDefinitionPolicy(), [$resolver]))->generate(Type::object(Library::class), $config);

        $this->assertSame(Author::class, $resolver->calls[2][0]);
        $this->assertNotSame($config, $resolver->configs[2]);
        $this->assertSame(['operation' => 'get_library'], $resolver->configs[2]->context);
    }

    public function testContextIsPassedToThePropertyInfoExtractor()
    {
        $extractor = new RecordingPropertyListExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $config = new Configuration(groups: ['author:read'], context: ['operation' => 'get_author', 'serializer_groups' => ['ignored']]);

        (new SchemaGenerator(new PropertyInfoExtractor([$extractor], [$reflectionExtractor], [], [$reflectionExtractor], [$reflectionExtractor])))->generate(Type::object(Author::class), $config);

        $this->assertSame([['operation' => 'get_author', 'serializer_groups' => ['author:read']]], $extractor->contexts);
    }

    public function testClassSchemaResolverCanReplaceNestedRelationsByAnIriReferenceWithoutDefinition()
    {
        $generator = new SchemaGenerator(self::createPhpDocPropertyInfo(), classSchemaResolvers: [new IriReferenceClassSchemaResolver(Author::class)]);

        $schema = $generator->generate(Type::object(BookWithAuthors::class));

        $this->assertSame(['BookWithAuthors'], array_keys($schema->getDefinitions()));
        $this->assertSameSchema(['type' => ['string', 'null'], 'format' => 'iri-reference'], $schema->getDefinitions()['BookWithAuthors']['properties']['coAuthor']);
        $this->assertSameSchema(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'iri-reference'], 'default' => []], $schema->getDefinitions()['BookWithAuthors']['properties']['reviewers']);
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

    #[DataProvider('provideBooleanItemsSchemas')]
    public function testChoiceOfManyRestrictsBooleanItemsSchemas(bool $items, array|bool $expectedItems)
    {
        $generator = SchemaGenerator::create(null, [], [new FixedPropertySchemaProvider(ContactWithFormatConstraints::class, 'channels', ['type' => 'array', 'items' => $items])]);

        $definition = $generator->generate(Type::object(ContactWithFormatConstraints::class))->getDefinitions()['ContactWithFormatConstraints'];

        $this->assertSame($expectedItems, $definition['properties']['channels']['items']);
    }

    public static function provideBooleanItemsSchemas(): iterable
    {
        yield 'any item' => [true, ['enum' => ['email', 'sms', 'push']]];
        yield 'no item' => [false, false];
    }

    public function testDefinitionNameReplacesTheGroupsSuffix()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(GroupedProduct::class), new Configuration(groups: ['product:read'], definitionName: 'Custom'));

        $this->assertSameSchema(['$ref' => '#/$defs/GroupedProduct-Custom'], $schema->getRoot());
    }

    public function testDisallowingExtraAttributesClosesTheObject()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(StrictInput::class), new Configuration(allowExtraAttributes: false));

        $this->assertFalse($schema->getDefinitions()['StrictInput-closed']['additionalProperties']);
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

        $this->assertSameSchema(['type' => ['string', 'null'], 'minLength' => 1, 'maxLength' => 10], $definition['properties']['invitationCode']);
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

    public function testDiscriminatorMapBecomesOneOfTheMappedClasses()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Shape::class));

        $this->assertSameSchema(['anyOf' => [
            ['$ref' => '#/$defs/Circle', 'required' => ['kind']],
            ['$ref' => '#/$defs/Square', 'required' => ['kind']],
        ]], $schema->getRoot());
        $this->assertSameSchema(['type' => 'string', 'const' => 'circle'], $schema->getDefinitions()['Circle']['properties']['kind']);
        $this->assertSameSchema(['type' => 'string', 'const' => 'square'], $schema->getDefinitions()['Square']['properties']['kind']);
    }

    public function testDefaultTypeOfADiscriminatorMapIsNotRequired()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Vehicle::class));

        $this->assertSameSchema(['anyOf' => [
            ['$ref' => '#/$defs/Car'],
            ['$ref' => '#/$defs/Truck', 'required' => ['type']],
        ]], $schema->getRoot());
    }

    public function testTypePropertyDeclaredByAMappedClassKeepsItsSchema()
    {
        $properties = SchemaGenerator::create()->generate(Type::object(Vehicle::class))->getDefinitions()['Truck']['properties'];

        $this->assertSame(['axles', 'type'], array_keys($properties));
        $this->assertSameSchema(['type' => 'string', 'default' => 'truck', 'description' => 'The kind of vehicle.', 'const' => 'truck'], $properties['type']);
    }

    public function testDiscriminatorBranchesWrapTheReferenceForOpenApi30()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Shape::class), new Configuration(Dialect::openApi30()));

        $this->assertSameSchema(['allOf' => [['$ref' => '#/components/schemas/Circle']], 'required' => ['kind']], $schema->getRoot()['anyOf'][0]);
        $this->assertSameSchema(['type' => 'string', 'enum' => ['circle']], $schema->getDefinitions()['Circle']['properties']['kind']);
    }

    public function testSerializedPathNestsThePropertyInObjects()
    {
        $definition = SchemaGenerator::create()->generate(Type::object(PublishedArticle::class))->getDefinitions()['PublishedArticle'];

        $this->assertSame(['body', 'meta'], array_keys($definition['properties']));
        $this->assertSame(['meta'], $definition['required']);
        $this->assertSameSchema([
            'type' => 'object',
            'properties' => [
                'author' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'default' => '']]],
                'title' => ['type' => 'string'],
            ],
            'required' => ['title'],
        ], $definition['properties']['meta']);
    }

    public function testEmptyArrayDefaultsFollowTheType()
    {
        $properties = SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class))->getDefinitions()['BookWithAuthors']['properties'];

        $this->assertSame('[]', json_encode($properties['reviewers']['default']));
        $this->assertSame('{}', json_encode($properties['authorsByRole']['default']));
    }

    public function testObjectDefaultValuesOfConstructorArgumentsAreNotInstantiated()
    {
        $properties = SchemaGenerator::create()->generate(Type::object(ServiceDefaults::class))->getDefinitions()['ServiceDefaults']['properties'];

        $this->assertArrayNotHasKey('default', $properties['service']);
        $this->assertSame('hearts', $properties['suit']['default']);
        $this->assertSame('default', $properties['name']['default']);
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
