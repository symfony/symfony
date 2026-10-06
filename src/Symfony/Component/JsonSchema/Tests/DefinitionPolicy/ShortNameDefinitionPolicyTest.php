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
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\Tests\Fixtures\Author;
use Symfony\Component\JsonSchema\Tests\Fixtures\Catalog\Product as CatalogProduct;
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory\Product as InventoryProduct;
use Symfony\Component\Validator\Constraints\GroupSequence;

class ShortNameDefinitionPolicyTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testNameFor(Configuration $config, string $expected)
    {
        $this->assertSame($expected, (new ShortNameDefinitionPolicy())->nameFor(Author::class, $config));
    }

    public static function provideNames(): iterable
    {
        yield 'short name' => [new Configuration(), 'Author'];
        yield 'groups' => [new Configuration(groups: ['author:read', 'author:list']), 'Author-author.read_author.list'];
        yield 'attributes' => [new Configuration(attributes: ['name', 'books' => ['title', 'isbn']]), 'Author-name_books.title_books.isbn'];
        yield 'validation groups' => [new Configuration(validationGroups: ['create', ['strict']]), 'Author-validation.create_strict'];
        yield 'group sequence' => [new Configuration(validationGroups: new GroupSequence(['create', 'strict'])), 'Author-validation.create_strict'];
        yield 'empty validation groups' => [new Configuration(validationGroups: []), 'Author-validation.none'];
        yield 'closure validation groups' => [new Configuration(validationGroups: static fn (): array => ['create']), 'Author'];
        yield 'groups and validation groups' => [new Configuration(groups: ['write'], validationGroups: ['create']), 'Author-write_validation.create'];
        yield 'definition name' => [new Configuration(groups: ['write'], definitionName: 'Custom'), 'Author-Custom'];
        yield 'empty definition name' => [new Configuration(groups: ['write'], definitionName: ''), 'Author'];
        yield 'partial' => [new Configuration(partial: true), 'Author.partial'];
        yield 'prefix' => [new Configuration(groups: ['read'], definitionPrefix: 'Writer'), 'Writer-read'];
    }

    public function testShortNameCollisionUsesMoreNamespaceParts()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration()));
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration()));
        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration()));
    }

    public function testPrefixCollisionFallsBackToTheClassName()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration(definitionPrefix: 'Product')));
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration(definitionPrefix: 'Product')));
    }

    public function testDefinitionPrefixOnlyNamesTheRoot()
    {
        $policy = new ShortNameDefinitionPolicy();
        $config = new Configuration(definitionPrefix: 'Writer');

        $this->assertSame('Writer', $policy->nameFor(Author::class, $config));
        $this->assertSame('Author', $policy->nameFor(Author::class, $config, new DefinitionParent(CatalogProduct::class, 'author')));
    }
}
