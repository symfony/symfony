<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\DefinitionPolicy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\Catalog\Product as CatalogProduct;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Envelope;
use Symfony\Component\JsonSchema\Tests\Fixtures\Generic\Pair;
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory\Product as InventoryProduct;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Validator\Constraints\GroupSequence;

class ShortNameDefinitionPolicyTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testNameFor(Configuration $config, string $expected)
    {
        $this->assertSame($expected, (new ShortNameDefinitionPolicy())->nameFor(Type::object(Author::class), $config, new SchemaDocument()));
    }

    public static function provideNames(): iterable
    {
        yield 'short name' => [new Configuration(), 'Author'];
        yield 'groups' => [new Configuration(groups: ['author:read', 'author:list']), 'Author-author.list_author.read'];
        yield 'attributes' => [new Configuration(attributes: ['name', 'books' => ['title', 'isbn']]), 'Author-name_books.title_books.isbn'];
        yield 'validation groups' => [new Configuration(validationGroups: ['create', ['strict']]), 'Author-validation.create_strict'];
        yield 'group sequence' => [new Configuration(validationGroups: new GroupSequence(['create', 'strict'])), 'Author-validation.create_strict'];
        yield 'empty validation groups' => [new Configuration(validationGroups: []), 'Author-validation.none'];
        yield 'ignored attributes' => [new Configuration(ignoredAttributes: ['id', 'books']), 'Author-ignored.books_id'];
        yield 'no extra attributes' => [new Configuration(allowExtraAttributes: false), 'Author-closed'];
        yield 'format' => [new Configuration(format: 'json'), 'Author-format.json'];
        yield 'direction' => [new Configuration(direction: Direction::Output), 'Author-output'];
        yield 'every option' => [new Configuration(groups: ['write'], attributes: ['name'], ignoredAttributes: ['id'], allowExtraAttributes: false, validationGroups: ['create'], format: 'json'), 'Author-write_name_ignored.id_closed_validation.create_format.json'];
        yield 'groups and validation groups' => [new Configuration(groups: ['write'], validationGroups: ['create']), 'Author-write_validation.create'];
        yield 'definition name' => [new Configuration(groups: ['write'], definitionName: 'Custom'), 'Author-Custom'];
        yield 'empty definition name' => [new Configuration(groups: ['write'], definitionName: ''), 'Author'];
        yield 'prefix' => [new Configuration(groups: ['read'], definitionPrefix: 'Writer'), 'Writer-read'];
    }

    public function testNamesDoNotDependOnTheOrderOfTheCalls()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $policy->nameFor(Type::object(Author::class), new Configuration(null, [], null, ['id']), $document);

        $this->assertSame('Author', $policy->nameFor(Type::object(Author::class), new Configuration(), $document));
        $this->assertSame('Author-ignored.id', $policy->nameFor(Type::object(Author::class), new Configuration(null, [], null, ['id']), $document));
    }

    public function testShortNameCollisionUsesMoreNamespaceParts()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();

        $this->assertSame('Product', $policy->nameFor(Type::object(CatalogProduct::class), new Configuration(), $document));
        $this->assertSame('Inventory.Product', $policy->nameFor(Type::object(InventoryProduct::class), new Configuration(), $document));
        $this->assertSame('Product', $policy->nameFor(Type::object(CatalogProduct::class), new Configuration(), $document));
    }

    public function testShortNamesAreClaimedPerDocument()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Product', $policy->nameFor(Type::object(CatalogProduct::class), new Configuration(), new SchemaDocument()));
        $this->assertSame('Product', $policy->nameFor(Type::object(InventoryProduct::class), new Configuration(), new SchemaDocument()));
    }

    public function testPrefixCollisionFallsBackToTheClassName()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();

        $this->assertSame('Product', $policy->nameFor(Type::object(CatalogProduct::class), new Configuration(null, [], null, [], true, null, null, 'Product'), $document));
        $this->assertSame('Inventory.Product', $policy->nameFor(Type::object(InventoryProduct::class), new Configuration(null, [], null, [], true, null, null, 'Product'), $document));
    }

    public function testDefinitionPrefixOnlyNamesTheRoot()
    {
        $policy = new ShortNameDefinitionPolicy();
        $config = new Configuration(definitionPrefix: 'Writer');

        $this->assertSame('Writer', $policy->nameFor(Type::object(Author::class), $config, new SchemaDocument()));
        $this->assertSame('Author', $policy->nameFor(Type::object(Author::class), $config, new SchemaDocument(), new DefinitionParent(CatalogProduct::class, 'author', $config)));
    }

    #[DataProvider('provideGenericNames')]
    public function testGenericNames(Type $type, Configuration $config, string $expected)
    {
        $this->assertSame($expected, (new ShortNameDefinitionPolicy())->nameFor($type, $config, new SchemaDocument()));
    }

    public static function provideGenericNames(): iterable
    {
        $string = Type::generic(Type::object(Envelope::class), Type::string());
        yield 'scalar' => [$string, new Configuration(), 'Envelope-of.string'];
        yield 'class' => [Type::generic(Type::object(Envelope::class), Type::object(Author::class)), new Configuration(), 'Envelope-of.Author'];
        yield 'pair' => [Type::generic(Type::object(Pair::class), Type::string(), Type::int()), new Configuration(), 'Pair-of.string.int'];
        yield 'nested generic' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::object(Envelope::class), Type::object(Author::class))), new Configuration(), 'Envelope-of.Envelope_Author'];
        yield 'nested pair' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::object(Pair::class), Type::string(), Type::int())), new Configuration(), 'Envelope-of.Pair_string_int'];
        yield 'list' => [Type::generic(Type::object(Envelope::class), Type::list(Type::object(Author::class))), new Configuration(), 'Envelope-of.list_Author'];
        yield 'nested lists' => [Type::generic(Type::object(Envelope::class), Type::list(Type::list(Type::string()))), new Configuration(), 'Envelope-of.list_list_string'];
        yield 'list of generics' => [Type::generic(Type::object(Envelope::class), Type::list(Type::generic(Type::object(Envelope::class), Type::object(Author::class)))), new Configuration(), 'Envelope-of.list_Envelope_Author'];
        yield 'generic with list argument' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::object(Pair::class), Type::list(Type::string()), Type::int())), new Configuration(), 'Envelope-of.Pair_list_string_int'];
        yield 'empty nested generic' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::object(Author::class))), new Configuration(), 'Envelope-of.Author'];
        yield 'generic array' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::int())), new Configuration(), 'Envelope-of.array_string_int'];
        yield 'generic iterable' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::builtin(TypeIdentifier::ITERABLE), Type::object(Author::class))), new Configuration(), 'Envelope-of.iterable_Author'];
        yield 'nested generic with prefix and groups' => [Type::generic(Type::object(Envelope::class), $string), new Configuration(null, ['read'], null, [], true, null, null, 'Result'), 'Result-of.Envelope_string-read'];
        yield 'maximum readable label' => [Type::generic(Type::object(Envelope::class), Type::generic(Type::object('Wrapper'), Type::object(str_repeat('A', 120)))), new Configuration(), 'Envelope-of.Wrapper_'.str_repeat('A', 120)];
        yield 'custom' => [$string, new Configuration(null, [], null, [], true, null, 'Custom'), 'Envelope-of.string-Custom'];
        yield 'empty custom' => [$string, new Configuration(null, [], null, [], true, null, ''), 'Envelope-of.string'];
        yield 'prefix' => [$string, new Configuration(null, [], null, [], true, null, null, 'Result'), 'Result-of.string'];
        yield 'groups' => [$string, new Configuration(null, ['read']), 'Envelope-of.string-read'];
        yield 'attributes' => [$string, new Configuration(null, [], ['name']), 'Envelope-of.string-name'];
        yield 'direction' => [$string, new Configuration(null, [], null, [], true, null, null, null, null, Direction::Output), 'Envelope-of.string-output'];
        yield 'empty arguments' => [Type::generic(Type::object(Envelope::class)), new Configuration(), 'Envelope'];
    }

    public function testArgumentLabelsDoNotClaimNamesAndSelectedNamesStayStable()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::object('App\\Enum\\Status'));
        $this->assertSame('Envelope-of.Status', $policy->nameFor($type, new Configuration(), $document));
        $this->assertSame('Status', $policy->nameFor(Type::object('App\\Dto\\Status'), new Configuration(), $document));
        $this->assertSame('Envelope-of.Status', $policy->nameFor($type, new Configuration(), $document));
        $this->assertSame('Nested-of.Enum.Status', $policy->nameFor($type, new Configuration(null, [], null, [], true, null, null, 'Nested'), $document));
        $otherDocument = new SchemaDocument();
        $policy->nameFor(Type::object('App\\Dto\\Status'), new Configuration(), $otherDocument);
        $this->assertSame('Envelope-of.Enum.Status', $policy->nameFor($type, new Configuration(), $otherDocument));
    }

    public function testGenericRootAndNestedPrefixesHaveSeparateSelections()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::string());
        $config = new Configuration(null, [], null, [], true, null, null, 'Result');
        $this->assertSame('Result-of.string', $policy->nameFor($type, $config, $document));
        $this->assertSame('Envelope-of.string', $policy->nameFor($type, $config, $document, new DefinitionParent(Author::class, 'result', $config)));
    }

    public function testNestedArgumentLabelsDoNotClaimNamesAndSelectedNamesStayStable()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $type = Type::generic(Type::object(Envelope::class), Type::generic(Type::object('App\\Input\\Wrapper'), Type::list(Type::object('App\\Enum\\Status'))));

        $this->assertSame('Envelope-of.Wrapper_list_Status', $policy->nameFor($type, new Configuration(), $document));
        $this->assertSame('Wrapper', $policy->nameFor(Type::object('App\\Dto\\Wrapper'), new Configuration(), $document));
        $this->assertSame('Status', $policy->nameFor(Type::object('App\\Dto\\Status'), new Configuration(), $document));
        $this->assertSame('Envelope-of.Wrapper_list_Status', $policy->nameFor($type, new Configuration(), $document));
        $this->assertSame('Result-of.Input.Wrapper_list_Enum.Status', $policy->nameFor($type, new Configuration(null, [], null, [], true, null, null, 'Result'), $document));
    }

    public function testRecursiveLabelCollisionsHaveStableDigestSuffixes()
    {
        $generic = Type::generic(Type::object(Envelope::class), Type::object(Author::class));
        $list = Type::list(Type::object(Author::class));
        foreach ([[$generic, Type::object('Envelope_Author'), 'Envelope_Author'], [$list, Type::object('list_Author'), 'list_Author']] as [$compound, $class, $label]) {
            foreach ([[$compound, $class], [$class, $compound]] as [$first, $second]) {
                $policy = new ShortNameDefinitionPolicy();
                $document = new SchemaDocument();
                $first = Type::generic(Type::object(Envelope::class), $first);
                $second = Type::generic(Type::object(Envelope::class), $second);
                $firstName = $policy->nameFor($first, new Configuration(), $document);
                $secondName = $policy->nameFor($second, new Configuration(), $document);

                $this->assertSame('Envelope-of.'.$label, $firstName);
                $this->assertSame($firstName.'.'.substr(hash('sha256', (string) $second), 0, 16), $secondName);
                $this->assertSame($firstName, $policy->nameFor($first, new Configuration(), $document));
                $this->assertSame($secondName, $policy->nameFor($second, new Configuration(), $document));
            }
        }
    }

    public function testReadableNameCollisionsHaveStableDigestSuffixes()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $policy->nameFor(Type::object('Foo'), new Configuration(), $document);
        $one = Type::generic(Type::object(Pair::class), Type::object('A\\Foo'));
        $two = Type::generic(Type::object(Pair::class), Type::object('A'), Type::object('Foo'));
        $first = $policy->nameFor($one, new Configuration(), $document);
        $second = $policy->nameFor($two, new Configuration(), $document);
        $this->assertSame('Pair-of.A.Foo', $first);
        $this->assertMatchesRegularExpression('/^Pair-of.A.Foo\.[a-f0-9]{16}$/', $second);
        $this->assertSame($first, $policy->nameFor($one, new Configuration(), $document));
        $this->assertSame($second, $policy->nameFor($two, new Configuration(), $document));
    }

    public function testSanitizedLabelsAndEqualShortNamesStayDistinct()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $one = Type::generic(Type::object(Envelope::class), Type::object('App\\First\\Status'));
        $two = Type::generic(Type::object(Envelope::class), Type::object('App\\Second\\Status'));
        $this->assertSame('Envelope-of.Status', $policy->nameFor($one, new Configuration(), $document));
        $this->assertNotSame('Envelope-of.Status', $policy->nameFor($two, new Configuration(), $document));
        $this->assertSame('Envelope-of.Status', $policy->nameFor($two, new Configuration(), new SchemaDocument()));
        $one = Type::generic(Type::object(Envelope::class), Type::object('Name!'));
        $two = Type::generic(Type::object(Envelope::class), Type::object('Name?'));
        $this->assertNotSame($policy->nameFor($one, new Configuration(), $document), $policy->nameFor($two, new Configuration(), $document));
    }

    #[DataProvider('provideComplexArgumentsAndLongLabels')]
    public function testComplexArgumentsAndLongLabelsUseHashedNames(Type $argument)
    {
        $policy = new ShortNameDefinitionPolicy();
        $type = Type::generic(Type::object(Envelope::class), $argument);
        $expected = 'Envelope-of.generic.'.substr(hash('sha256', serialize([(string) $argument])), 0, 16);

        $this->assertSame($expected, $policy->nameFor($type, new Configuration(), new SchemaDocument()));
    }

    public static function provideComplexArgumentsAndLongLabels(): iterable
    {
        yield 'array shape' => [Type::arrayShape(["a'\\b" => Type::string()])];
        yield 'list-shaped array shape' => [Type::arrayShape([Type::string()])];
        yield 'union' => [Type::union(Type::int(), Type::string())];
        yield 'nullable' => [Type::nullable(Type::string())];
        yield 'map' => [Type::array(Type::string(), Type::int())];
        yield 'list of shapes' => [Type::list(Type::arrayShape(['name' => Type::string()]))];
        yield 'generic with shape argument' => [Type::generic(Type::object(Pair::class), Type::string(), Type::arrayShape(['name' => Type::string()]))];
        yield 'long class name' => [Type::object(str_repeat('A', 129))];
        yield 'long recursive label' => [Type::generic(Type::object('Wrapper'), Type::object(str_repeat('A', 121)))];
    }

    public function testEqualArgumentsGetTheSameName()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();
        $shared = Type::object(Author::class);

        $this->assertSame($policy->nameFor(Type::generic(Type::object(Pair::class), $shared, $shared), new Configuration(), $document), $policy->nameFor(Type::generic(Type::object(Pair::class), Type::object(Author::class), Type::object(Author::class)), new Configuration(), $document));
        $this->assertSame($policy->nameFor(Type::generic(Type::object(Envelope::class), Type::union(Type::string(), Type::int())), new Configuration(), $document), $policy->nameFor(Type::generic(Type::object(Envelope::class), Type::union(Type::int(), Type::string())), new Configuration(), $document));
    }
}
