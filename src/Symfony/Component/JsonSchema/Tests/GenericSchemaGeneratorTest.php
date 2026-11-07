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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstantNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\FixedPropertySchemaProvider;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Customer;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Envelope;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Inbox;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\RecordingDefinitionProcessor;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;

#[RequiresMethod(Type::class, 'map')]
class GenericSchemaGeneratorTest extends TestCase
{
    public function testGenericPropertiesHaveSeparateConcreteDefinitions()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Inbox::class));
        $definitions = $schema->getDefinitions();

        $this->assertSame(['$ref' => '#/$defs/Envelope-of.string'], $definitions['Inbox']['properties']['message']);
        $this->assertSame(['$ref' => '#/$defs/Envelope-of.Customer'], $definitions['Inbox']['properties']['customer']);
        $this->assertSame(['type' => 'string'], $definitions['Envelope-of.string']['properties']['data']);
        $this->assertSame(['$ref' => '#/$defs/Customer'], $definitions['Envelope-of.Customer']['properties']['data']);
        $this->assertSame(['type' => 'string'], $definitions['Customer']['properties']['name']);
        $this->assertSame(['type' => 'string'], $definitions['Envelope-of.Customer']['properties']['status']);
    }

    public function testGenericRoot()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Envelope::class), Type::object(Customer::class)));

        $this->assertSame(['$ref' => '#/$defs/Envelope-of.Customer'], $schema->getRoot());
        $this->assertSame(['$ref' => '#/$defs/Customer'], $schema->getDefinitions()['Envelope-of.Customer']['properties']['data']);
    }

    public function testRawRecursiveGenericsKeepTheirOriginalDefinition()
    {
        $generator = SchemaGenerator::create();
        foreach ([Generic\Node::class, Generic\GrowingNode::class, Generic\DoublingNode::class] as $class) {
            $schema = $generator->generate(Type::object($class));
            $name = (new \ReflectionClass($class))->getShortName();

            $this->assertSame(['$ref' => '#/$defs/'.$name], $schema->getRoot());
            $this->assertSame([$name], array_keys($schema->getDefinitions()));
            $this->assertStringContainsString('#/$defs/'.$name, json_encode($schema->getDefinitions()[$name]['properties']['next'], \JSON_UNESCAPED_SLASHES));
        }
    }

    #[DataProvider('provideInheritedEnvelopes')]
    public function testInheritedPropertiesUseTheChildTemplateBindings(string $class)
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object($class), Type::int(), Type::object(Customer::class)))->flatten();
        $properties = $schema->getRoot()['properties'];

        $this->assertSame('object', $properties['data']['type']);
        $this->assertSame(['name' => ['type' => 'string']], $properties['data']['properties']);
        $this->assertSame(['type' => 'integer'], $properties['extra']);
    }

    public static function provideInheritedEnvelopes(): iterable
    {
        yield 'property' => [Generic\InheritedEnvelope::class];
        yield 'getter' => [Generic\InheritedAccessorEnvelope::class];
        yield 'setter behind a local getter' => [Generic\InheritedReadEnvelope::class];
        yield 'getter behind local storage' => [Generic\InheritedStorageEnvelope::class];
        yield 'setter behind a promoted property' => [Generic\InheritedPromotedEnvelope::class];
    }

    public function testInheritedConcreteArgumentsDoNotUseSameNamedChildTemplates()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\FixedParentEnvelope::class), Type::int()))->flatten();

        $this->assertSame(['type' => 'string'], $schema->getRoot()['properties']['data']);
        $this->assertSame(['type' => 'integer'], $schema->getRoot()['properties']['extra']);
    }

    public function testNestedArgumentsThroughMultipleParents()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\GrandchildEnvelope::class), Type::object(Customer::class)))->flatten();

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]], $schema->getRoot()['properties']['data']);
        $this->assertSame(['type' => 'integer'], $schema->getRoot()['properties']['extra']);
    }

    public function testTraitArgumentsUseTheClassTemplates()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\TraitEnvelope::class), Type::string()))->flatten();

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $schema->getRoot()['properties']['data']);
    }

    public function testInterfaceArgumentsUseTheClassTemplates()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\InterfaceEnvelope::class), Type::string()))->flatten();

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string'], 'readOnly' => true], $schema->getRoot()['properties']['data']);
    }

    #[DataProvider('provideUntaggedEnvelopes')]
    public function testUntaggedParentsDoNotUseDifferentlyNamedChildTemplates(string $class, array|\ArrayObject $expected)
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object($class), Type::string()))->flatten();

        $this->assertEquals($expected, $schema->getRoot()['properties']['data']);
    }

    public static function provideUntaggedEnvelopes(): iterable
    {
        yield 'class' => [Generic\UntaggedChildEnvelope::class, new \ArrayObject()];
        yield 'interface' => [Generic\UntaggedInterfaceEnvelope::class, ['readOnly' => true]];
        yield 'trait' => [Generic\UntaggedTraitEnvelope::class, new \ArrayObject()];
    }

    #[DataProvider('provideInheritedNonPublicAccessors')]
    public function testInheritedNonPublicAccessorsUseTheChildTemplateBindings(string $property)
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\InheritedNonPublicAccessorEnvelope::class), Type::int()))->flatten();
        $properties = $schema->getRoot()['properties'];

        $this->assertSame(['type' => 'integer'], $properties['localRead']);
        $this->assertSame(['type' => 'integer'], $properties['localWrite']);
        $this->assertSame(['type' => 'integer'], $properties[$property]);
    }

    public static function provideInheritedNonPublicAccessors(): iterable
    {
        yield 'protected getter' => ['protectedRead'];
        yield 'private getter' => ['privateRead'];
        yield 'protected setter' => ['protectedWrite'];
        yield 'private setter' => ['privateWrite'];
    }

    public function testDeepUnboundArgumentsKeepTheRawDefinition()
    {
        $argument = Type::template('T');
        for ($i = 0; $i < 33; ++$i) {
            $argument = Type::list($argument);
        }
        $generator = self::generatorForProperty(Type::generic(Type::object(Envelope::class), $argument));
        $schema = $generator->generate(Type::object(Envelope::class));

        $this->assertSame(['Envelope'], array_keys($schema->getDefinitions()));
        $this->assertSame(['$ref' => '#/$defs/Envelope'], $schema->getDefinitions()['Envelope']['properties']['data']);
    }

    public function testDeepConcreteArgumentsUseTheRawDefinition()
    {
        $argument = Type::int();
        for ($i = 0; $i < 33; ++$i) {
            $argument = Type::list($argument);
        }
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $generator->generate(Type::object(Customer::class), new Configuration(), $document);
        $before = $document->getDefinitions()['Customer'];
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), $argument), new Configuration(), $document);

        $this->assertSame(['$ref' => '#/$defs/Envelope'], $schema->getRoot());
        $this->assertEquals(new \ArrayObject(), $schema->getDefinitions()['Envelope']['properties']['data']);
        $this->assertSame($before, $document->getDefinitions()['Customer']);
        $this->assertEquals(['Customer' => Type::object(Customer::class), 'Envelope' => Type::object(Envelope::class)], $document->getDefinitionTypes());
    }

    public function testOrdinaryDefinitionDepthDoesNotLimitGenericProperties()
    {
        $properties = [];
        for ($i = 0; $i < 70; ++$i) {
            $properties['Acme\\Schema\\Level'.$i] = ['next' => 69 === $i ? Type::generic(Type::object(Envelope::class), Type::int()) : Type::object('Acme\\Schema\\Level'.($i + 1))];
        }
        $generator = self::generatorForPropertyMap($properties);
        $schema = $generator->generate(Type::object('Acme\\Schema\\Level0'), new Configuration(null, [], null, [], true, null, null, null, null, Direction::Output));

        $this->assertCount(71, $schema->getDefinitions());
        $this->assertSame(['type' => 'integer'], $schema->getDefinitions()['Envelope-of.int-output']['properties']['data']);
    }

    public function testInheritedCollectionMutatorsUseTheChildTemplateBindings()
    {
        $generator = SchemaGenerator::create();
        $properties = $generator->generate(Type::generic(Type::object(Generic\InheritedCollectionEnvelope::class), Type::int(), Type::string()))->flatten()->getRoot()['properties'];

        $this->assertSame(['type' => 'integer'], $properties['extra']);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $properties['item']);
    }

    public function testInheritedConstructorUsesTheChildTemplateBindings()
    {
        $phpDoc = new PhpStanExtractor();
        $reflection = new ReflectionExtractor();
        $generator = new SchemaGenerator(new PropertyInfoExtractor([$reflection], [new ConstructorExtractor([$phpDoc]), $phpDoc, $reflection], [], [$reflection], [$reflection]));
        $properties = $generator->generate(Type::generic(Type::object(Generic\InheritedConstructorEnvelope::class), Type::int(), Type::string()))->flatten()->getRoot()['properties'];

        $this->assertSame(['type' => 'string'], $properties['data']);
        $this->assertSame(['type' => 'integer'], $properties['extra']);
    }

    #[DataProvider('provideVirtualProperties')]
    public function testVirtualPropertiesUseTheClassTemplateBindings(string $class, string $template, string $expected)
    {
        $generator = self::generatorForPropertyMap([$class => ['virtual' => Type::template($template, Type::int())]]);
        $properties = $generator->generate(Type::generic(Type::object($class), Type::string(), Type::bool()))->flatten()->getRoot()['properties'];

        $this->assertSame(['type' => $expected], $properties['virtual']);
    }

    public static function provideVirtualProperties(): iterable
    {
        yield 'root class' => [Generic\Phantom::class, 'T', 'string'];
        yield 'child class' => [Generic\ChildEnvelope::class, 'T', 'string'];
        yield 'contravariant declaration' => [Generic\ContravariantTemplates::class, 'A', 'string'];
    }

    #[DataProvider('provideSameStringCompoundMembers')]
    public function testSubstitutionPreservesSameStringCompoundMembers(bool $intersection)
    {
        $generic = Type::generic(Type::object(Generic\Bag::class), Type::string());
        $collection = Type::collection($generic);
        $template = Type::template('T', Type::object(Generic\Bag::class));
        $declared = $intersection ? Type::intersection($template, $collection) : Type::union($template, $collection);
        $schema = self::generatorForProperty($declared)->generate(Type::generic(Type::object(Envelope::class), $generic))->flatten();

        $this->assertSame((string) $generic, (string) $collection);
        $this->assertEqualsCanonicalizing([
            SchemaGenerator::create()->generate($generic)->flatten()->getRoot(),
            ['type' => 'array', 'items' => ['type' => 'string']],
        ], $schema->getRoot()['properties']['data'][$intersection ? 'allOf' : 'anyOf']);
    }

    public static function provideSameStringCompoundMembers(): iterable
    {
        yield 'union' => [false];
        yield 'intersection' => [true];
    }

    #[DataProvider('provideUnboundShapeArguments')]
    public function testUnboundShapeArgumentsKeepTheRawDefinition(Type $argument)
    {
        $generator = self::generatorForProperty(Type::generic(Type::object(Envelope::class), $argument));
        $schema = $generator->generate(Type::object(Envelope::class));

        $this->assertSame(['Envelope'], array_keys($schema->getDefinitions()));
        $this->assertSame(['$ref' => '#/$defs/Envelope'], $schema->getDefinitions()['Envelope']['properties']['data']);
    }

    public static function provideUnboundShapeArguments(): iterable
    {
        $t = Type::template('T');
        yield 'shape entry hidden by mixed' => [Type::arrayShape(['value' => $t, 'other' => Type::mixed()])];
        yield 'extra shape value hidden by mixed' => [new Type\ArrayShapeType(['other' => ['type' => Type::mixed(), 'optional' => false]], Type::string(), $t)];
        yield 'extra shape key' => [new Type\ArrayShapeType([], Type::template('T', Type::arrayKey()), Type::int())];
        if (class_exists(Type\ObjectShapeType::class)) {
            yield 'object shape entry hidden by mixed' => [Type::objectShape(['value' => $t, 'other' => Type::mixed()])];
        }
    }

    public function testSameNameInheritedTemplatesUseTheChildTemplateBindings()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\ChildEnvelope::class), Type::int()))->flatten();

        $this->assertSame(['type' => 'integer'], $schema->getRoot()['properties']['data']);
        $this->assertSame(['type' => 'string'], $schema->getRoot()['properties']['status']);
    }

    public function testRepeatedSpecializationsAndRawTypesInOneDocument()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $first = $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()), new Configuration(), $document);
        $again = $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()), new Configuration(), $document);
        $this->assertSame($first->getRoot(), $again->getRoot());
        $this->assertCount(1, $document->getDefinitions());
        foreach ([Type::object(Envelope::class), Type::generic(Type::object(Envelope::class)), Type::generic(Type::object(Envelope::class), Type::mixed())] as $type) {
            $generator->generate($type, new Configuration(), $document);
        }
        $this->assertSame(['Envelope-of.string', 'Envelope', 'Envelope-of.mixed'], array_keys($document->getDefinitions()));
        $this->assertEquals(new \ArrayObject(), $document->getDefinitions()['Envelope']['properties']['data']);
        $this->assertEquals(new \ArrayObject(), $document->getDefinitions()['Envelope-of.mixed']['properties']['data']);
        $this->assertSame([Envelope::class, Envelope::class, Envelope::class], array_values($document->getDefinitionClasses()));
    }

    #[DataProvider('provideWideShapeArguments')]
    public function testWideShapeArgumentsRemainSupported(Type $argument, int $fields, Type $value)
    {
        $generator = SchemaGenerator::create();
        $data = $generator->generate(Type::generic(Type::object(Envelope::class), $argument))->flatten()->getRoot()['properties']['data'];
        $expected = $generator->generate($value)->flatten()->getRoot();

        $this->assertCount($fields, $data['properties']);
        $this->assertSame(array_keys($data['properties']), $data['required']);
        foreach ($data['properties'] as $property) {
            $this->assertEquals($expected, $property);
        }
    }

    public static function provideWideShapeArguments(): iterable
    {
        foreach ([26, 128] as $fields) {
            $shape = [];
            for ($i = 0; $i < $fields; ++$i) {
                $shape['field'.$i] = Type::list(Type::object(Customer::class));
            }
            yield $fields.' lists' => [Type::arrayShape($shape), $fields, Type::list(Type::object(Customer::class))];
        }
        $shape = [];
        for ($i = 0; $i < 43; ++$i) {
            $shape['field'.$i] = Type::nullable(Type::string());
        }
        yield '43 nullable strings' => [Type::arrayShape($shape), 43, Type::nullable(Type::string())];
    }

    public function testWideSubstitutedPropertiesRemainSupported()
    {
        $shape = [];
        for ($i = 0; $i < 26; ++$i) {
            $shape['field'.$i] = Type::template('T');
        }
        $generator = self::generatorForProperty(Type::arrayShape($shape));
        $data = $generator->generate(Type::generic(Type::object(Envelope::class), Type::list(Type::object(Customer::class))))->flatten()->getRoot()['properties']['data'];

        $this->assertCount(26, $data['properties']);
        $this->assertSame(['type' => 'string'], $data['properties']['field25']['items']['properties']['name']);
    }

    public function testArgumentsAtTheNestingLimitRemainSupported()
    {
        $argument = Type::string();
        for ($i = 0; $i < 63; ++$i) {
            $argument = Type::generic(Type::object(Envelope::class), $argument);
        }
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Envelope::class), $argument));

        $this->assertCount(64, $schema->getDefinitions());
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['Envelope-of.string']['properties']['data']);
    }

    public function testArgumentDepthIsNotSummedAcrossTemplateParameters()
    {
        $argument = Type::string();
        for ($i = 0; $i < 39; ++$i) {
            $argument = Type::generic(Type::object(Envelope::class), $argument);
        }
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\Pair::class), $argument, $argument));
        $definitions = $schema->getDefinitions();
        $name = substr($schema->getRoot()['$ref'], \strlen('#/$defs/'));

        $this->assertCount(40, $definitions);
        $this->assertSame($definitions[$name]['properties']['first'], $definitions[$name]['properties']['second']);
    }

    public function testArgumentSizeCountsSharedOccurrencesAcrossAllArguments()
    {
        $argument = Type::arrayShape(array_fill(0, 511, Type::int()));
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $schema = $generator->generate(Type::generic(Type::object(Generic\Pair::class), $argument, $argument), new Configuration(), $document);
        $properties = $schema->flatten()->getRoot()['properties'];
        $this->assertEquals($properties['first'], $properties['second']);
        $this->assertSame(511, $properties['first']['minItems']);
        $this->assertSame(511, $properties['first']['maxItems']);
        $this->assertSame(['type' => 'integer'], $properties['first']['items']);
        $before = $document->getDefinitions();
        $types = $document->getDefinitionTypes();
        $fallback = $generator->generate(Type::generic(Type::object(Generic\Pair::class), $argument, $argument, Type::int()), new Configuration(), $document);

        $this->assertSame(['$ref' => '#/$defs/Pair'], $fallback->getRoot());
        $this->assertEquals(new \ArrayObject(), $fallback->getDefinitions()['Pair']['properties']['first']);
        $this->assertSame(['type' => 'integer'], $fallback->getDefinitions()['Pair']['properties']['second']);
        $this->assertSame($before, array_diff_key($document->getDefinitions(), ['Pair' => true]));
        $this->assertSame($types, array_diff_key($document->getDefinitionTypes(), ['Pair' => true]));
        $this->assertEquals(Type::object(Generic\Pair::class), $document->getDefinitionTypes()['Pair']);
    }

    #[DataProvider('provideStructuredArguments')]
    public function testStructuredArgumentsKeepSeparateDefinitions(Type $first, Type $second)
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $firstSchema = $generator->generate(Type::generic(Type::object(Envelope::class), $first), new Configuration(), $document);
        $secondSchema = $generator->generate(Type::generic(Type::object(Envelope::class), $second), new Configuration(), $document);

        $this->assertNotSame($firstSchema->getRoot(), $secondSchema->getRoot());
        $this->assertCount(2, $document->getDefinitions());
        $this->assertEquals($generator->generate($first)->getRoot(), $firstSchema->flatten()->getRoot()['properties']['data']);
        $this->assertEquals($generator->generate($second)->getRoot(), $secondSchema->flatten()->getRoot()['properties']['data']);
    }

    public static function provideStructuredArguments(): iterable
    {
        $required = Type::arrayShape(['item' => Type::string()]);
        yield 'shape key' => [$required, Type::arrayShape(['other' => Type::string()])];
        yield 'shape value' => [$required, Type::arrayShape(['item' => Type::int()])];
        yield 'optionality' => [$required, Type::arrayShape(['item' => ['type' => Type::string(), 'optional' => true]])];
        yield 'sealing' => [$required, Type::arrayShape(['item' => Type::string()], true, Type::string(), Type::mixed())];
        yield 'extra value' => [Type::arrayShape(['item' => Type::string()], true, Type::string(), Type::int()), Type::arrayShape(['item' => Type::string()], true, Type::string(), Type::string())];
        yield 'extra key' => [Type::arrayShape(['item' => Type::string()], true, Type::int(), Type::string()), Type::arrayShape(['item' => Type::string()], true, Type::string(), Type::string())];
        yield 'list flag' => [Type::list(Type::string()), Type::array(Type::string(), Type::int())];
        if (class_exists(Type\ObjectShapeType::class)) {
            yield 'shape kind' => [$required, Type::objectShape(['item' => Type::string()])];
        }
    }

    public function testUnboundTemplateArgumentsKeepTheRawTypeForObjectHooks()
    {
        $policy = new RecordingDefinitionPolicy();
        $resolver = new RecordingClassSchemaResolver();
        $processor = new RecordingDefinitionProcessor();
        $generator = SchemaGenerator::create($policy, [$resolver], [], [], [$processor]);
        $document = new SchemaDocument();
        $raw = Type::object(Envelope::class);
        $first = $generator->generate(Type::generic($raw, Type::template('CallerT', Type::string())), new Configuration(), $document);
        $second = $generator->generate($raw, new Configuration(), $document);

        $this->assertSame(['$ref' => '#/$defs/Envelope'], $first->getRoot());
        $this->assertSame($first->getRoot(), $second->getRoot());
        $this->assertCount(1, $document->getDefinitions());
        $this->assertEquals([[$raw, null], [$raw, null]], $policy->calls);
        $this->assertEquals([[$raw, null], [$raw, null]], $resolver->calls);
        $this->assertEquals([[$raw, null]], $processor->calls);
    }

    /**
     * @param list<Type>                          $arguments
     * @param array<string, array<string, mixed>> $expected
     */
    #[DataProvider('provideTemplateDeclarations')]
    public function testTemplateDeclarationPositions(string $class, array $arguments, array $expected)
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object($class), ...$arguments))->flatten();
        $this->assertSame($expected, $schema->getRoot()['properties']);
    }

    public static function provideTemplateDeclarations(): iterable
    {
        yield 'missing bound argument' => [Generic\Pair::class, [Type::string()], ['first' => ['type' => 'string'], 'second' => ['type' => 'integer']]];
        yield 'extra argument' => [Generic\Pair::class, [Type::bool(), Type::string(), Type::float()], ['first' => ['type' => 'boolean'], 'second' => ['type' => 'string']]];
        yield 'reversed arguments' => [Generic\Pair::class, [Type::int(), Type::string()], ['first' => ['type' => 'integer'], 'second' => ['type' => 'string']]];
        yield 'source order and repeated vendor alias' => [Generic\MixedTemplates::class, [Type::string(), Type::int(), Type::bool()], ['first' => ['type' => 'string'], 'second' => ['type' => 'integer'], 'third' => ['type' => 'boolean']]];
        yield 'repeated alias with an extra argument' => [Generic\MixedTemplates::class, [Type::string(), Type::int(), Type::bool(), Type::float()], ['first' => ['type' => 'string'], 'second' => ['type' => 'integer'], 'third' => ['type' => 'boolean']]];
        yield 'contravariant slot' => [Generic\ContravariantTemplates::class, [Type::int(), Type::string()], ['second' => ['type' => 'string']]];
        yield 'accessor PHPDoc' => [Generic\AccessorEnvelope::class, [Type::string()], ['data' => ['type' => 'string', 'readOnly' => true]]];
        yield 'mutator PHPDoc' => [Generic\MutatorEnvelope::class, [Type::string()], ['data' => ['type' => 'string', 'writeOnly' => true]]];
        yield 'promoted PHPDoc' => [Generic\PromotedEnvelope::class, [Type::int()], ['data' => ['type' => 'integer']]];
    }

    public function testCovariantSlotsFollowExtractionCapability()
    {
        $type = (new PhpStanExtractor())->getType(Generic\CovariantTemplates::class, 'first');
        $generator = SchemaGenerator::create();
        $properties = $generator->generate(Type::generic(Type::object(Generic\CovariantTemplates::class), Type::int(), Type::string()))->flatten()->getRoot()['properties'];
        $this->assertSame(['type' => 'string'], $properties['second']);
        if ($type instanceof TemplateType) {
            $this->assertSame(['type' => 'integer'], $properties['first']);
        } else {
            $this->assertNotSame(['type' => 'integer'], $properties['first']);
            $raw = $generator->generate(Type::object(Generic\CovariantTemplates::class))->flatten()->getRoot()['properties']['first'];
            $this->assertEquals($raw, $properties['first']);
        }
    }

    public function testNestedBindingsAreLocalToEachDefinition()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\NestedEnvelope::class), Type::object(Customer::class)));
        $definitions = $schema->getDefinitions();
        $this->assertSame(['$ref' => '#/$defs/Envelope-of.Customer'], $definitions['NestedEnvelope-of.Customer']['properties']['inner']);
        $this->assertEquals(new \ArrayObject(), $definitions['Envelope']['properties']['data']);
        $this->assertSame(['$ref' => '#/$defs/Customer'], $definitions['Envelope-of.Customer']['properties']['data']);
        $nested = SchemaGenerator::create()->generate(Type::generic(Type::object(Envelope::class), Type::generic(Type::object(Envelope::class), Type::object(Customer::class))))->flatten();
        $this->assertSame(['type' => 'string'], $nested->getRoot()['properties']['data']['properties']['data']['properties']['name']);
    }

    public function testNestedGenericArgumentsHaveReadableNamesAndReusableDefinitions()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $customer = Type::object(Customer::class);
        $inner = Type::generic(Type::object(Envelope::class), $customer);
        $cases = [
            [$inner, 'Envelope_Customer', ['$ref' => '#/$defs/Envelope-of.Customer']],
            [Type::list($customer), 'list_Customer', ['type' => 'array', 'items' => ['$ref' => '#/$defs/Customer']]],
            [Type::generic(Type::object(Generic\Pair::class), $customer, Type::int(), Type::float()), 'Pair_Customer_int_float', ['$ref' => '#/$defs/Pair-of.Customer.int.float']],
        ];
        foreach ($cases as [$argument, $label, $data]) {
            $type = Type::generic(Type::object(Envelope::class), $argument);
            $schema = $generator->generate($type, new Configuration(), $document);

            $this->assertSame(['$ref' => '#/$defs/Envelope-of.'.$label], $schema->getRoot());
            $this->assertSame($data, $schema->getDefinitions()['Envelope-of.'.$label]['properties']['data']);
            $this->assertEquals($type, $document->getDefinitionTypes()['Envelope-of.'.$label]);
            $this->assertSame($schema->getRoot(), $generator->generate($type, new Configuration(), $document)->getRoot());
        }
        $this->assertCount(6, $document->getDefinitions());
    }

    public function testTypeAwareHooksSeePhantomArguments()
    {
        $policy = new RecordingDefinitionPolicy();
        $resolver = new RecordingClassSchemaResolver();
        $processor = new RecordingDefinitionProcessor();
        $generator = SchemaGenerator::create($policy, [$resolver], [], [], [$processor]);
        $document = new SchemaDocument();
        foreach ([Type::string(), Type::int()] as $argument) {
            $type = Type::generic(Type::object(Generic\Phantom::class), $argument);
            $generator->generate($type, new Configuration(), $document);
            $generator->generate($type, new Configuration(), $document);
        }
        $this->assertEquals(Type::generic(Type::object(Generic\Phantom::class), Type::string()), $processor->calls[0][0]);
        $this->assertEquals(Type::generic(Type::object(Generic\Phantom::class), Type::int()), $processor->calls[1][0]);
        $this->assertEquals($processor->calls[1][0], $policy->calls[2][0]);
        $this->assertEquals($processor->calls[1][0], $resolver->calls[2][0]);
        $this->assertCount(2, $processor->calls);
    }

    public function testResolverCanDescribeAGenericArgument()
    {
        $resolver = new class implements ClassSchemaResolverInterface {
            /**
             * @return array<string, mixed>|null
             */
            public function resolve(ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
            {
                return $type instanceof GenericType && Generic\Phantom::class === $type->getWrappedType()->getClassName() ? $document->describe(Type::generic(Type::object(Envelope::class), $type->getVariableTypes()[0]), $config, $parent) : null;
            }
        };
        $schema = SchemaGenerator::create(null, [$resolver])->generate(Type::generic(Type::object(Generic\Phantom::class), Type::string()));
        $this->assertSame(['$ref' => '#/$defs/Envelope-of.string'], $schema->getRoot());
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['Envelope-of.string']['properties']['data']);
    }

    public function testProcessorCanDescribeGenericTypes()
    {
        $processor = new class implements DefinitionProcessorInterface {
            /**
             * @param array<string, mixed> $definition
             *
             * @return array<string, mixed>
             */
            public function process(array $definition, ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
            {
                if ($type instanceof GenericType && Generic\Phantom::class === $type->getWrappedType()->getClassName()) {
                    $definition['properties']['data'] = $document->describe(Type::generic(Type::object(Envelope::class), $type->getVariableTypes()[0]), $config, $parent);
                }

                return $definition;
            }
        };
        $schema = SchemaGenerator::create(null, [], [], [], [$processor])->generate(Type::generic(Type::object(Generic\Phantom::class), Type::int()));
        $this->assertSame(['$ref' => '#/$defs/Envelope-of.int'], $schema->getDefinitions()['Phantom-of.int']['properties']['data']);
        $this->assertSame(['type' => 'integer'], $schema->getDefinitions()['Envelope-of.int']['properties']['data']);
    }

    public function testCustomPolicyCollisionRollsBack()
    {
        $generator = SchemaGenerator::create(new ConstantNameDefinitionPolicy('Same'));
        $document = new SchemaDocument();
        $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()), new Configuration(), $document);
        $before = $document->getDefinitions();
        try {
            $generator->generate(Type::generic(Type::object(Envelope::class), Type::int()), new Configuration(), $document);
            $this->fail('Different arguments must not share a definition.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('different type or configuration', $e->getMessage());
        }
        $this->assertEquals($before, $document->getDefinitions());
    }

    public function testEnrichersReceiveConcreteTypesEvenWithAProvider()
    {
        $enricher = new class implements PropertySchemaEnricherInterface {
            public ?Type $seen = null;

            public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
            {
                if ('data' === $property->property) {
                    $this->seen = $property->type;
                }

                return $property;
            }
        };
        $generator = SchemaGenerator::create(null, [], [new FixedPropertySchemaProvider(Envelope::class, 'data', ['type' => 'string', 'format' => 'custom'])], [$enricher]);
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::int()));
        $this->assertEquals(Type::int(), $enricher->seen);
        $this->assertSame(['type' => 'string', 'format' => 'custom'], $schema->getDefinitions()['Envelope-of.int']['properties']['data']);
        $generator->generate(Type::object(Envelope::class));
        $this->assertInstanceOf(TemplateType::class, $enricher->seen);
    }

    public function testValidatorConstraintsUseSubstitutedPropertyTypes()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\ConstrainedEnvelope::class), Type::string()), new Configuration(null, ['public'], ['data'], [], true, null, null, null, null, Direction::Input))->flatten();
        $this->assertSame(1, $schema->getRoot()['properties']['value']['minLength']);
    }

    #[DataProvider('provideDialects')]
    public function testGenericRecursionInEachDialect(Dialect $dialect)
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\Node::class), Type::string()), new Configuration($dialect));
        $this->assertCount(1, $schema->getDefinitions());
        $this->assertSame(['$ref' => $dialect->refPath.'Node-of.string'], $schema->getRoot());
        $this->assertStringContainsString($dialect->refPath.'Node-of.string', json_encode($schema->flatten(), \JSON_UNESCAPED_SLASHES));
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['Node-of.string']['properties']['data']);
    }

    public static function provideDialects(): iterable
    {
        yield [Dialect::jsonSchema202012()];
        yield [Dialect::openApi31()];
        yield [Dialect::openApi30()];
    }

    public function testFiniteArgumentTransitionsAndMutualRecursion()
    {
        $generator = SchemaGenerator::create();
        $schema = $generator->generate(Type::generic(Type::object(Generic\FixedNode::class), Type::string()));
        $this->assertSame(['FixedNode-of.string', 'FixedNode-of.int'], array_keys($schema->getDefinitions()));
        $schema = $generator->generate(Type::generic(Type::object(Generic\MutualNode::class), Type::int()));
        $this->assertSame(['MutualNode-of.int', 'OtherNode-of.int'], array_keys($schema->getDefinitions()));
    }

    #[DataProvider('provideGrowingNodes')]
    public function testExpandingRecursionUsesTheRawDefinition(string $class)
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $raw = $generator->generate(Type::object($class));
        $schema = $generator->generate(Type::generic(Type::object($class), Type::string()), new Configuration(), $document);
        $name = (new \ReflectionClass($class))->getShortName();
        $definitions = $schema->getDefinitions();

        $this->assertSame(['$ref' => '#/$defs/'.$name.'-of.string'], $schema->getRoot());
        $this->assertArrayHasKey($name, $definitions);
        $this->assertSame($raw->getDefinitions()[$name], $definitions[$name]);
        $this->assertStringContainsString('#/$defs/'.$name, json_encode($definitions[$name]['properties']['next'], \JSON_UNESCAPED_SLASHES));
        $this->assertEquals(Type::object($class), $document->getDefinitionTypes()[$name]);
        $this->assertSame(['type' => 'string'], $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()), new Configuration(), $document)->getDefinitions()['Envelope-of.string']['properties']['data']);
    }

    public static function provideGrowingNodes(): iterable
    {
        yield [Generic\GrowingNode::class];
        yield [Generic\DoublingNode::class];
    }

    #[DataProvider('provideParserCallOrders')]
    public function testParameterDescriptionsAndGenericTypesWorkInEitherOrder(bool $parametersFirst)
    {
        $generator = SchemaGenerator::create();
        $parameters = (new \ReflectionMethod(Generic\GenericTool::class, '__invoke'))->getParameters();
        $type = Type::generic(Type::object(Envelope::class), Type::int());
        if ($parametersFirst) {
            $schema = $generator->generateForParameters($parameters);
            $generator->generate($type);
        } else {
            $generator->generate($type);
            $schema = $generator->generateForParameters($parameters);
        }
        $property = $schema->getRoot()['properties']['input'];
        $this->assertSame('#/$defs/Envelope-of.string', $property['$ref']);
        $this->assertSame(class_exists(ParserConfig::class) ? 'The input envelope.' : null, $property['description'] ?? null);
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['Envelope-of.string']['properties']['data']);
    }

    public static function provideParserCallOrders(): iterable
    {
        yield [false];
        yield [true];
    }

    public function testIterableClassesKeepCollectionDispatch()
    {
        $schema = SchemaGenerator::create()->generate(Type::object(Generic\Collections::class));
        $properties = $schema->getDefinitions()['Collections']['properties'];
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $properties['bag']);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $properties['arrayAccess']);
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/$defs/Envelope-of.string']], $properties['envelopes']);
        $this->assertSame(['Collections', 'Envelope-of.string'], array_keys($schema->getDefinitions()));
        $explicit = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\Bag::class), Type::string()));
        $this->assertSame(['$ref' => '#/$defs/Bag-of.string'], $explicit->getRoot());
    }

    public function testReflectionOnlyAndUnavailableClasses()
    {
        $reflection = new ReflectionExtractor();
        $generator = new SchemaGenerator(new PropertyInfoExtractor([$reflection], [$reflection], [], [$reflection], [$reflection]));
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()));
        $this->assertEquals(new \ArrayObject(), $schema->getDefinitions()['Envelope-of.string']['properties']['data']);
        $schema = $generator->generate(Type::generic(Type::object('UnavailableGenericClass'), Type::int()));
        $this->assertSame(['type' => 'object'], $schema->getDefinitions()['UnavailableGenericClass-of.int']);
    }

    #[DataProvider('provideUnsupportedIntersectionArguments')]
    public function testUnsupportedConstructionRollsBack(Type $argument)
    {
        $type = Type::intersection(Type::template('T', Type::object(Customer::class)), Type::object(\Stringable::class));
        $generator = self::generatorForProperty($type);
        $document = new SchemaDocument();
        try {
            $generator->generate(Type::generic(Type::object(Envelope::class), $argument), new Configuration(), $document);
            $this->fail('Unsupported substitutions must throw.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString(Envelope::class.'::$data', $e->getMessage());
            $this->assertInstanceOf(\Symfony\Component\TypeInfo\Exception\InvalidArgumentException::class, $e->getPrevious());
        }
        $this->assertSame([], $document->getDefinitions());
    }

    public static function provideUnsupportedIntersectionArguments(): iterable
    {
        yield 'union distribution' => [Type::union(Type::object(Customer::class), Type::object(Envelope::class))];
        yield 'nullable object' => [Type::nullable(Type::object(Customer::class))];
        yield 'scalar' => [Type::int()];
    }

    public function testConcreteExtractorTypesAreRespected()
    {
        $document = new SchemaDocument();
        $generator = self::generatorForProperty(Type::string(), Generic\ChildEnvelope::class);
        $schema = $generator->generate(Type::generic(Type::object(Generic\ChildEnvelope::class), Type::int()), new Configuration(), $document);
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['ChildEnvelope-of.int']['properties']['data']);
    }

    public function testLargeFinitePropertiesDoNotAcquireExpansionBudgets()
    {
        $shape = array_fill(0, 1025, Type::string());
        $shape['template'] = Type::template('T');
        $generator = self::generatorForProperty(Type::arrayShape($shape));
        $raw = $generator->generate(Type::object(Envelope::class))->flatten();
        $specialized = $generator->generate(Type::generic(Type::object(Envelope::class), Type::int()))->flatten();

        $this->assertCount(1026, $specialized->getRoot()['properties']['data']['properties']);
        $this->assertEquals(new \ArrayObject(), $raw->getRoot()['properties']['data']['properties']['template']);
        $this->assertSame(['type' => 'integer'], $specialized->getRoot()['properties']['data']['properties']['template']);
    }

    public function testNamingChoicesSurviveRollbackAndFailedDefinitionsCanBeRetried()
    {
        $policy = new ShortNameDefinitionPolicy();
        $processor = new class implements DefinitionProcessorInterface {
            public bool $fail = true;

            /**
             * @param array<string, mixed> $definition
             *
             * @return array<string, mixed>
             */
            public function process(array $definition, ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
            {
                if ($this->fail && $type instanceof GenericType) {
                    throw new \RuntimeException('Failure from an extension.');
                }

                return $definition;
            }
        };
        $generator = SchemaGenerator::create($policy, [], [], [], [$processor]);
        $document = new SchemaDocument();
        $generator->generate(Type::object('Foo'), new Configuration(), $document);
        $first = Type::generic(Type::object(Generic\Phantom::class), Type::object('A\\Foo'));
        $second = Type::generic(Type::object(Generic\Phantom::class), Type::object('A'), Type::object('Foo'));
        try {
            $generator->generate($first, new Configuration(), $document);
            $this->fail('The extension must fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Failure from an extension.', $e->getMessage());
        }
        $this->assertSame(['Foo'], array_keys($document->getDefinitions()));
        $processor->fail = false;
        $suffix = $generator->generate($second, new Configuration(), $document)->getRoot()['$ref'];
        $this->assertMatchesRegularExpression('~^#/\$defs/Phantom-of.A.Foo\.[a-f0-9]{16}$~', $suffix);
        $this->assertSame(['$ref' => '#/$defs/Phantom-of.A.Foo'], $generator->generate($first, new Configuration(), $document)->getRoot());
        $this->assertArrayHasKey('Phantom-of.A.Foo', $document->getDefinitions());
        $this->assertSame($suffix, $generator->generate($second, new Configuration(), $document)->getRoot()['$ref']);
        $fresh = new SchemaDocument();
        $generator->generate(Type::object('Foo'), new Configuration(), $fresh);
        $this->assertSame(['$ref' => '#/$defs/Phantom-of.A.Foo'], $generator->generate($second, new Configuration(), $fresh)->getRoot());
    }

    public function testDiscriminatorSelfBranchKeepsArguments()
    {
        $schema = SchemaGenerator::create()->generate(Type::generic(Type::object(Generic\DiscriminatedEnvelope::class), Type::string()));
        $this->assertSame(['type' => 'string'], $schema->getDefinitions()['DiscriminatedEnvelope-of.string']['properties']['data']);
        $this->assertArrayHasKey('DiscriminatorChild', $schema->getDefinitions());
        $this->assertArrayNotHasKey('DiscriminatorChild-of.string', $schema->getDefinitions());
    }

    #[DataProvider('provideParserCallOrders')]
    public function testInlineAndExcludedArgumentsDoNotClaimOrdinaryNames(bool $dtoFirst)
    {
        foreach ([new Configuration(), new Configuration(null, [], ['status'])] as $config) {
            $generator = SchemaGenerator::create();
            $document = new SchemaDocument();
            if ($dtoFirst) {
                $generator->generate(Type::object(Generic\Dto\Status::class), new Configuration(), $document);
            }
            $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::enum(Generic\Enum\Status::class)), $config, $document);
            if (!$dtoFirst) {
                $generator->generate(Type::object(Generic\Dto\Status::class), new Configuration(), $document);
            }
            $this->assertSame(Generic\Dto\Status::class, $document->getDefinitionClasses()['Status']);
            $this->assertNotContains(Generic\Enum\Status::class, $document->getDefinitionClasses());
            $this->assertCount(1, $schema->getDefinitions());
        }
    }

    public function testUnrepresentableSubstitutionsAreFilteredAfterBinding()
    {
        $generator = SchemaGenerator::create();
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::callable()));
        $this->assertSame(['status' => ['type' => 'string']], $schema->getDefinitions()['Envelope-of.callable']['properties']);
        $generator = self::generatorForProperty(Type::union(Type::template('T'), Type::string()));
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::callable()))->flatten();
        $this->assertSame(['type' => 'string'], $schema->getRoot()['properties']['data']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPhpDocDependenciesRemainOptional()
    {
        $loaders = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
        foreach ($loaders as $loader) {
            $loader->unregister();
        }
        spl_autoload_register(static function (string $class) use ($loaders): void {
            if (str_starts_with($class, 'PHPStan\\PhpDocParser\\') || str_starts_with($class, 'phpDocumentor\\')) {
                return;
            }
            foreach ($loaders as $loader) {
                if ($loader->loadClass($class)) {
                    return;
                }
            }
        });

        $generator = SchemaGenerator::create();
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()));
        $this->assertEquals(new \ArrayObject(), $schema->getDefinitions()['Envelope-of.string']['properties']['data']);
        $parameters = $generator->generateForParameters((new \ReflectionMethod(Generic\GenericTool::class, '__invoke'))->getParameters());
        $this->assertArrayNotHasKey('description', $parameters->getRoot()['properties']['input']);
    }

    /**
     * @param array{type: string, format: string} $expected
     */
    #[DataProvider('provideInlineArguments')]
    public function testOtherInlineArgumentsDoNotClaimOrdinaryNames(string $inlineClass, string $ordinaryClass, array $expected, ?ClassSchemaResolverInterface $resolver, bool $ordinaryFirst)
    {
        $generator = SchemaGenerator::create(null, $resolver ? [$resolver] : []);
        $document = new SchemaDocument();
        if ($ordinaryFirst) {
            $generator->generate(Type::object($ordinaryClass), new Configuration(), $document);
        }
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), Type::object($inlineClass)), new Configuration(), $document);
        if (!$ordinaryFirst) {
            $generator->generate(Type::object($ordinaryClass), new Configuration(), $document);
        }
        $shortName = (new \ReflectionClass($ordinaryClass))->getShortName();
        $this->assertSame($ordinaryClass, $document->getDefinitionClasses()[$shortName]);
        $this->assertNotContains($inlineClass, $document->getDefinitionClasses());
        $this->assertCount(1, $schema->getDefinitions());
        $definition = array_values($schema->getDefinitions())[0];
        $this->assertSame($expected, $definition['properties']['data']);
    }

    public static function provideInlineArguments(): iterable
    {
        foreach ([
            'date' => [\DateTimeImmutable::class, Generic\Dto\DateTimeImmutable::class, ['type' => 'string', 'format' => 'date-time'], null],
            'UID' => [\Symfony\Component\Uid\Uuid::class, Generic\Dto\Uuid::class, ['type' => 'string', 'format' => 'uuid'], null],
            'custom resolver' => [Generic\Inline\Status::class, Generic\Dto\Status::class, ['type' => 'string', 'format' => 'iri-reference'], new Fixtures\IriReferenceClassSchemaResolver(Generic\Inline\Status::class)],
        ] as $name => $arguments) {
            yield $name.' before DTO' => [...$arguments, false];
            yield $name.' after DTO' => [...$arguments, true];
        }
    }

    #[DataProvider('providePropertySubstitutions')]
    public function testPropertySubstitutionMatchesTheConcreteType(Type $declared, Type $argument, Type $expected)
    {
        $schema = self::generatorForProperty($declared)->generate(Type::generic(Type::object(Envelope::class), $argument))->flatten();
        $expectedSchema = SchemaGenerator::create()->generate($expected)->flatten();

        $this->assertEquals($expectedSchema->getRoot(), $schema->getRoot()['properties']['data']);
    }

    public static function providePropertySubstitutions(): iterable
    {
        $t = Type::template('T');
        yield 'bound fallback' => [Type::template('U', Type::int()), Type::string(), Type::int()];
        yield 'bound referencing another local template' => [Type::template('U', $t), Type::string(), Type::string()];
        yield 'list' => [Type::list($t), Type::string(), Type::list(Type::string())];
        yield 'map' => [Type::dict($t), Type::int(), Type::dict(Type::int())];
        yield 'generic' => [Type::generic(Type::object(Generic\PromotedEnvelope::class), $t), Type::string(), Type::generic(Type::object(Generic\PromotedEnvelope::class), Type::string())];
        yield 'collection wrapper' => [Type::collection(Type::object(Generic\Bag::class), $t), Type::string(), Type::collection(Type::object(Generic\Bag::class), Type::string())];
        yield 'union duplicate' => [Type::union($t, Type::string()), Type::string(), Type::string()];
        yield 'union mixed' => [Type::union($t, Type::string()), Type::mixed(), Type::mixed()];
        yield 'union never' => [Type::union($t, Type::string()), Type::never(), Type::string()];
        yield 'flatten union' => [Type::union($t, Type::bool()), Type::union(Type::string(), Type::int()), Type::union(Type::string(), Type::int(), Type::bool())];
        yield 'true and false' => [Type::union($t, Type::false()), Type::true(), Type::bool()];
        yield 'bool absorbs literal' => [Type::union($t, Type::bool()), Type::true(), Type::bool()];
        yield 'object absorbs class' => [Type::union($t, Type::object(Customer::class)), Type::object(), Type::object()];
        yield 'nullable nullable' => [Type::nullable($t), Type::nullable(Type::string()), Type::nullable(Type::string())];
        yield 'nullable null' => [Type::nullable($t), Type::null(), Type::null()];
        yield 'nullable mixed' => [Type::nullable($t), Type::mixed(), Type::mixed()];
        yield 'nullable never' => [Type::nullable($t), Type::never(), Type::null()];
        yield 'sealed shape' => [Type::arrayShape(['value' => $t, 'optional' => ['type' => Type::list($t), 'optional' => true]]), Type::string(), Type::arrayShape(['value' => Type::string(), 'optional' => ['type' => Type::list(Type::string()), 'optional' => true]])];
        yield 'unsealed shape' => [new Type\ArrayShapeType(['value' => ['type' => $t, 'optional' => false]], Type::string(), $t), Type::int(), new Type\ArrayShapeType(['value' => ['type' => Type::int(), 'optional' => false]], Type::string(), Type::int())];
        yield 'extra shape key' => [new Type\ArrayShapeType([], Type::template('T', Type::arrayKey()), Type::int()), Type::string(), new Type\ArrayShapeType([], Type::string(), Type::int())];
        foreach ([false, true] as $unsealed) {
            $shape = Type::arrayShape([$t, Type::collection(Type::object(Generic\Bag::class), Type::int())])->getShape();
            $expected = Type::arrayShape([Type::object(), Type::collection(Type::object(Generic\Bag::class), Type::int())])->getShape();
            yield 'positional shape '.($unsealed ? 'with extra values' : 'sealed') => [new Type\ArrayShapeType($shape, $unsealed ? Type::int() : null, $unsealed ? Type::bool() : null), Type::object(), new Type\ArrayShapeType($expected, $unsealed ? Type::int() : null, $unsealed ? Type::bool() : null)];
        }
        $objectT = Type::template('T', Type::object(Customer::class));
        yield 'object intersection' => [Type::intersection($objectT, Type::object(\Stringable::class)), Type::object(Generic\PromotedEnvelope::class), Type::intersection(Type::object(Generic\PromotedEnvelope::class), Type::object(\Stringable::class))];
        yield 'intersection duplicate' => [Type::intersection($objectT, Type::object(\Stringable::class)), Type::object(\Stringable::class), Type::object(\Stringable::class)];
        yield 'flatten intersection' => [Type::intersection($objectT, Type::object(\Stringable::class)), Type::intersection(Type::object(Generic\PromotedEnvelope::class), Type::object(\Countable::class)), Type::intersection(Type::object(Generic\PromotedEnvelope::class), Type::object(\Countable::class), Type::object(\Stringable::class))];
        if (class_exists(Type\ObjectShapeType::class)) {
            yield 'object shape' => [Type::objectShape(['value' => $t, 'maybe' => ['type' => $t, 'optional' => true]]), Type::string(), Type::objectShape(['value' => Type::string(), 'maybe' => ['type' => Type::string(), 'optional' => true]])];
        }
    }

    /**
     * @param class-string $class
     */
    private static function generatorForProperty(Type $type, string $class = Envelope::class): SchemaGenerator
    {
        $extractor = new class($type, $class) implements PropertyTypeExtractorInterface {
            public function __construct(private readonly Type $type, private readonly string $class)
            {
            }

            /**
             * @param array<string, mixed> $context
             */
            public function getType(string $class, string $property, array $context = []): ?Type
            {
                return $this->class === $class && 'data' === $property ? $this->type : null;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return list<\Symfony\Component\PropertyInfo\Type>|null
             */
            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                return null;
            }
        };
        $reflection = new ReflectionExtractor();

        return new SchemaGenerator(new PropertyInfoExtractor([$reflection], [$extractor, new PhpStanExtractor(), $reflection], [], [$reflection], [$reflection]));
    }

    /**
     * @param array<class-string, array<string, Type>> $properties
     */
    private static function generatorForPropertyMap(array $properties): SchemaGenerator
    {
        $extractor = new class($properties) implements PropertyListExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface {
            /**
             * @param array<class-string, array<string, Type>> $properties
             */
            public function __construct(private readonly array $properties)
            {
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return list<string>|null
             */
            public function getProperties(string $class, array $context = []): ?array
            {
                return isset($this->properties[$class]) ? array_keys($this->properties[$class]) : null;
            }

            /**
             * @param array<string, mixed> $context
             */
            public function getType(string $class, string $property, array $context = []): ?Type
            {
                return $this->properties[$class][$property] ?? null;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return list<\Symfony\Component\PropertyInfo\Type>|null
             */
            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                return null;
            }

            /**
             * @param array<string, mixed> $context
             */
            public function isReadable(string $class, string $property, array $context = []): ?bool
            {
                return isset($this->properties[$class][$property]) ? true : null;
            }

            /**
             * @param array<string, mixed> $context
             */
            public function isWritable(string $class, string $property, array $context = []): ?bool
            {
                return isset($this->properties[$class][$property]) ? true : null;
            }
        };
        $reflection = new ReflectionExtractor();

        return new SchemaGenerator(new PropertyInfoExtractor([$extractor, $reflection], [$extractor, new PhpStanExtractor(), $reflection], [], [$extractor, $reflection], [$reflection]));
    }
}
