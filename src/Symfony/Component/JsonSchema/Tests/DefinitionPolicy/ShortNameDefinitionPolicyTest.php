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
        yield 'groups and validation groups' => [new Configuration(groups: ['write'], validationGroups: ['create']), 'Author-write_validation.create'];
        yield 'definition name' => [new Configuration(groups: ['write'], definitionName: 'Custom'), 'Author-Custom'];
        yield 'empty definition name' => [new Configuration(groups: ['write'], definitionName: ''), 'Author'];
        yield 'prefix' => [new Configuration(groups: ['read'], definitionPrefix: 'Writer'), 'Writer-read'];
    }

    public function testDifferentShapesNamedAlikeGetANumberedSuffix()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Author-first_name', $policy->nameFor(Author::class, new Configuration(attributes: ['first_name'])));
        $this->assertSame('Author-first_name.2', $policy->nameFor(Author::class, new Configuration(attributes: ['first', 'name'])));
    }

    public function testSameShapeAskedTwiceKeepsItsName()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Author-first_name', $policy->nameFor(Author::class, new Configuration(attributes: ['first_name'])));
        $this->assertSame('Author-first_name.2', $policy->nameFor(Author::class, new Configuration(attributes: ['first', 'name'])));
        $this->assertSame('Author-first_name', $policy->nameFor(Author::class, new Configuration(attributes: ['first_name'])));
        $this->assertSame('Author-first_name.2', $policy->nameFor(Author::class, new Configuration(attributes: ['first', 'name'])));
    }

    public function testIgnoredAttributesDisambiguateTheName()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Author', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['name'])));
        $this->assertSame('Author.2', $policy->nameFor(Author::class, new Configuration()));
    }

    public function testEachDistinctShapeGetsTheNextSuffix()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Author', $policy->nameFor(Author::class, new Configuration()));
        $this->assertSame('Author.2', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['name'])));
        $this->assertSame('Author.3', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['id'])));
    }

    public function testResetForgetsTheShapes()
    {
        $policy = new ShortNameDefinitionPolicy();
        $policy->nameFor(Author::class, new Configuration());
        $this->assertSame('Author.2', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['name'])));

        $policy->reset();

        $this->assertSame('Author', $policy->nameFor(Author::class, new Configuration(ignoredAttributes: ['name'])));
    }

    #[DataProvider('provideShapesDifferingOutsideTheName')]
    public function testShapesDifferingOutsideTheNameDisambiguate(Configuration $first, Configuration $second)
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Author', $policy->nameFor(Author::class, $first));
        $this->assertSame('Author.2', $policy->nameFor(Author::class, $second));
    }

    public static function provideShapesDifferingOutsideTheName(): iterable
    {
        yield 'extra attributes' => [new Configuration(allowExtraAttributes: false), new Configuration()];
        yield 'format' => [new Configuration(format: 'json'), new Configuration()];
    }

    public function testShortNameCollisionUsesMoreNamespaceParts()
    {
        $policy = new ShortNameDefinitionPolicy();

        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration()));
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration()));
        $this->assertSame('Product', $policy->nameFor(CatalogProduct::class, new Configuration()));
    }

    public function testResetGivesTheShortNameBackToTheNextClassClaimingIt()
    {
        $policy = new ShortNameDefinitionPolicy();
        $policy->nameFor(CatalogProduct::class, new Configuration());
        $this->assertSame('Inventory.Product', $policy->nameFor(InventoryProduct::class, new Configuration()));

        $policy->reset();

        $this->assertSame('Product', $policy->nameFor(InventoryProduct::class, new Configuration()));
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
        $this->assertSame('Author', $policy->nameFor(Author::class, $config, new DefinitionParent(CatalogProduct::class, 'author', $config)));
    }
}
