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
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory\Product as InventoryProduct;
use Symfony\Component\Validator\Constraints\GroupSequence;

class ShortNameDefinitionPolicyTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testNameFor(Configuration $config, string $expected)
    {
        $this->assertSame($expected, (new ShortNameDefinitionPolicy())->nameFor(Author::class, $config, new SchemaDocument()));
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
        $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['id']), $document);

        $this->assertSame('Author', $policy->nameFor(Author::class, new Configuration(), $document));
        $this->assertSame('Author-ignored.id', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['id']), $document));
    }

    public function testShortNameCollisionUsesMoreNamespaceParts()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration(), $document));
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration(), $document));
        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration(), $document));
    }

    public function testShortNamesAreClaimedPerDocument()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration(), new SchemaDocument()));
        $this->assertSame('Product', $policy->nameFor(InventoryProduct::class, new Configuration(), new SchemaDocument()));
    }

    public function testPrefixCollisionFallsBackToTheClassName()
    {
        $policy = new ShortNameDefinitionPolicy();
        $document = new SchemaDocument();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration(definitionPrefix: 'Product'), $document));
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration(definitionPrefix: 'Product'), $document));
    }

    public function testDefinitionPrefixOnlyNamesTheRoot()
    {
        $policy = new ShortNameDefinitionPolicy();
        $config = new Configuration(definitionPrefix: 'Writer');

        $this->assertSame('Writer', $policy->nameFor(Author::class, $config, new SchemaDocument()));
        $this->assertSame('Author', $policy->nameFor(Author::class, $config, new SchemaDocument(), new DefinitionParent(CatalogProduct::class, 'author', $config)));
    }
}
