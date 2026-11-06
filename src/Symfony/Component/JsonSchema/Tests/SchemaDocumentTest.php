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
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\BookWithAuthors;
use Symfony\Component\JsonSchema\Tests\Fixtures\ConstantNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\FailingPropertySchemaProvider;
use Symfony\Component\JsonSchema\Tests\Fixtures\FallbackClassSchemaResolver;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Customer;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Envelope;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Node;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Phantom;
use Symfony\Component\JsonSchema\Tests\Fixtures\Library;
use Symfony\Component\JsonSchema\Tests\Fixtures\Shelf;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

class SchemaDocumentTest extends TestCase
{
    public function testNewDocumentHasNoDefinitionTypes()
    {
        $this->assertSame([], (new SchemaDocument())->getDefinitionTypes());
    }

    public function testDefinitionTypesForOrdinaryClasses()
    {
        $document = new SchemaDocument();
        SchemaGenerator::create()->generate(Type::object(BookWithAuthors::class), new Configuration(), $document);

        $this->assertEquals(['BookWithAuthors' => Type::object(BookWithAuthors::class), 'Author' => Type::object(Author::class)], $document->getDefinitionTypes());
        $this->assertSame(['BookWithAuthors' => BookWithAuthors::class, 'Author' => Author::class], $document->getDefinitionClasses());
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesKeepGenericArguments()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $raw = Type::object(Envelope::class);
        $string = Type::generic($raw, Type::string());
        $integer = Type::generic($raw, Type::int());
        $customer = Type::generic($raw, Type::object(Customer::class));

        foreach ([$raw, $string, $integer, $customer] as $type) {
            $generator->generate($type, new Configuration(), $document);
        }

        $this->assertEquals([
            'Envelope' => $raw,
            'Envelope-of.string' => $string,
            'Envelope-of.int' => $integer,
            'Envelope-of.Customer' => $customer,
            'Customer' => Type::object(Customer::class),
        ], $document->getDefinitionTypes());
        $this->assertSame([
            'Envelope' => Envelope::class,
            'Envelope-of.string' => Envelope::class,
            'Envelope-of.int' => Envelope::class,
            'Envelope-of.Customer' => Envelope::class,
            'Customer' => Customer::class,
        ], $document->getDefinitionClasses());
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesDoNotDependOnDefinitionNames()
    {
        $argument = Type::arrayShape(['payload' => Type::nullable(Type::list(Type::object(Customer::class)))]);
        $type = Type::generic(Type::object(Phantom::class), $argument);
        $document = new SchemaDocument();
        SchemaGenerator::create(new ConstantNameDefinitionPolicy('Custom'))->generate($type, new Configuration(), $document);

        $this->assertEquals(['Custom' => $type], $document->getDefinitionTypes());
        $this->assertSame($argument, $document->getDefinitionTypes()['Custom']->getVariableTypes()[0]);
        $this->assertSame(['Custom' => Phantom::class], $document->getDefinitionClasses());
    }

    public function testDefinitionTypesContainTheNormalizedObjectTypes()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $raw = Type::object(Envelope::class);
        $generator->generate(Type::generic($raw), new Configuration(), $document);
        $generator->generate(Type::generic($raw, Type::template('T', Type::string())), new Configuration(), $document);

        $this->assertEquals(['Envelope' => $raw], $document->getDefinitionTypes());
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesForDifferentConfigurations()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::string());

        $generator->generate($type, new Configuration(), $document);
        $generator->generate($type, new Configuration(null, ['read']), $document);
        $before = $document->getDefinitionTypes();
        $generator->generate(Type::generic(Type::object(Envelope::class), Type::string()), new Configuration(), $document);

        $this->assertEquals(['Envelope-of.string' => $type, 'Envelope-of.string-read' => $type], $document->getDefinitionTypes());
        $this->assertSame($before, $document->getDefinitionTypes());
    }

    #[DataProvider('provideEquivalentArguments')]
    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesKeepTheFirstEquivalentType(Type $first, Type $second)
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();
        $schema = $generator->generate(Type::generic(Type::object(Envelope::class), $first), new Configuration(), $document);
        $before = $document->getDefinitionTypes();
        $this->assertSame($first::class, $before['Envelope-of.Status']->getVariableTypes()[0]::class);

        $reused = $generator->generate(Type::generic(Type::object(Envelope::class), $second), new Configuration(), $document);

        $this->assertSame($schema->getRoot(), $reused->getRoot());
        $this->assertSame($schema->getDefinitions(), $reused->getDefinitions());
        $this->assertSame($before, $document->getDefinitionTypes());
        $this->assertSame(['Envelope-of.Status'], array_keys($document->getDefinitions()));
    }

    public static function provideEquivalentArguments(): iterable
    {
        $object = Type::object(Fixtures\Generic\Enum\Status::class);
        $enum = Type::enum(Fixtures\Generic\Enum\Status::class);

        yield 'object first' => [$object, $enum];
        yield 'enum first' => [$enum, $object];
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesAreAvailableWhileBuildingRecursiveDefinitions()
    {
        $processor = new class implements DefinitionProcessorInterface {
            /**
             * @var array<string, ObjectType|GenericType<ObjectType>>
             */
            public array $types = [];

            /**
             * @param array<string, mixed> $definition
             *
             * @return array<string, mixed>
             */
            public function process(array $definition, ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
            {
                $this->types = $document->getDefinitionTypes();

                return $definition;
            }
        };
        $type = Type::generic(Type::object(Node::class), Type::string());
        $document = new SchemaDocument();
        SchemaGenerator::create(null, [], [], [], [$processor])->generate($type, new Configuration(), $document);

        $this->assertEquals(['Node-of.string' => $type], $processor->types);
        $this->assertSame($processor->types, $document->getDefinitionTypes());
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testFailedGenerationRestoresDefinitionTypes()
    {
        $generator = SchemaGenerator::create(null, [], [new FailingPropertySchemaProvider(Customer::class)]);
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::string());
        $generator->generate($type, new Configuration(), $document);
        $before = $document->getDefinitionTypes();

        try {
            $generator->generate(Type::generic(Type::object(Envelope::class), Type::object(Customer::class)), new Configuration(), $document);
            $this->fail('The generation should have failed.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame($before, $document->getDefinitionTypes());
        $this->assertEquals(['Envelope-of.string' => $type], $before);

        $recovered = Type::generic(Type::object(Envelope::class), Type::object(Customer::class));
        SchemaGenerator::create()->generate($recovered, new Configuration(), $document);

        $this->assertEquals($recovered, $document->getDefinitionTypes()['Envelope-of.Customer']);
        $this->assertEquals(Type::object(Customer::class), $document->getDefinitionTypes()['Customer']);
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testNameCollisionsDoNotReplaceDefinitionTypes()
    {
        $generator = SchemaGenerator::create(new ConstantNameDefinitionPolicy('Custom'));
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::string());
        $generator->generate($type, new Configuration(), $document);

        try {
            $generator->generate(Type::generic(Type::object(Envelope::class), Type::int()), new Configuration(), $document);
            $this->fail('Different arguments must not share a definition.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('already describes class "'.Envelope::class.'"', $e->getMessage());
        }

        $this->assertEquals(['Custom' => $type], $document->getDefinitionTypes());
        $this->assertSame(['Custom' => Envelope::class], $document->getDefinitionClasses());
    }

    public function testFailedNestedDescriptionsLeaveNoDefinitionTypeBehind()
    {
        $generator = SchemaGenerator::create(null, [new FallbackClassSchemaResolver(Shelf::class, Author::class)], [new FailingPropertySchemaProvider(Author::class)]);
        $document = new SchemaDocument();
        $type = Type::object(Library::class);
        $generator->generate($type, new Configuration(), $document);

        $this->assertSame(['Library' => $type], $document->getDefinitionTypes());
    }

    public function testInlineSchemasHaveNoDefinitionTypes()
    {
        $generator = SchemaGenerator::create();
        $document = new SchemaDocument();

        foreach ([Type::object(\DateTimeImmutable::class), Type::enum(Fixtures\Generic\Enum\Status::class), Type::int(), Type::object()] as $type) {
            $generator->generate($type, new Configuration(), $document);
        }

        $this->assertSame([], $document->getDefinitionTypes());
    }

    #[RequiresMethod(Type::class, 'map')]
    public function testDefinitionTypesBelongToTheirDocument()
    {
        $generator = SchemaGenerator::create();
        $first = new SchemaDocument();
        $second = new SchemaDocument();
        $string = Type::generic(Type::object(Envelope::class), Type::string());
        $integer = Type::generic(Type::object(Envelope::class), Type::int());
        $generator->generate($string, new Configuration(), $first);
        $generator->generate($integer, new Configuration(), $second);

        $this->assertEquals(['Envelope-of.string' => $string], $first->getDefinitionTypes());
        $this->assertEquals(['Envelope-of.int' => $integer], $second->getDefinitionTypes());
    }
}
