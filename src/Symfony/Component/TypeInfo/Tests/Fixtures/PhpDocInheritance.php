<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures\PhpDocInheritance;

class Item
{
}

class A
{
}

class B
{
}

class ParentDummy
{
    /**
     * Returns the items of the parent.
     *
     * @return list<Item>
     */
    public function getItems(): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getUntyped()
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getAddedNative()
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getDescribed(): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getDeprecated(): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getInheritDoc(): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getOwn(): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    public function getChain(): array
    {
        return [];
    }

    /**
     * @return list<Item>|null
     */
    public function getNullable(): ?array
    {
        return null;
    }

    /**
     * @return iterable<Item>
     */
    public function getIterable(): iterable
    {
        return [];
    }

    /**
     * @return self
     */
    public function getNativeSelf(): self
    {
        return $this;
    }

    /**
     * @return ($value is null ? int : string)
     */
    public function getUnsupported(?int $value = null): int|string
    {
        return 0;
    }

    /**
     * @param list<Item> $tags
     */
    public function setTags(array $tags): void
    {
    }

    /**
     * @param list<Item> $tags
     */
    public function setRenamed(array $tags): void
    {
    }

    /**
     * @param list<Item> $tags
     */
    public function setWidened(array $tags): void
    {
    }

    /**
     * @param list<Item> $first
     * @param list<Item> $second
     *
     * @return list<Item>
     */
    public function merge(array $first, array $second): array
    {
        return [];
    }

    /**
     * @return list<Item>
     */
    private function getPrivate(): array
    {
        return [];
    }
}

class ChildDummy extends ParentDummy
{
    public function getItems(): array
    {
        return [];
    }

    public function getUntyped()
    {
        return [];
    }

    public function getAddedNative(): array
    {
        return [];
    }

    /**
     * Returns the items.
     */
    public function getDescribed(): array
    {
        return [];
    }

    /**
     * @deprecated
     */
    public function getDeprecated(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getInheritDoc(): array
    {
        return [];
    }

    /**
     * @return array<string, Item>
     */
    public function getOwn(): array
    {
        return [];
    }

    public function getNullable(): array
    {
        return [];
    }

    public function getIterable(): array
    {
        return [];
    }

    public function getNativeSelf(): self
    {
        return $this;
    }

    public function getUnsupported(?int $value = null): int|string
    {
        return 0;
    }

    public function getPrivate(): array
    {
        return [];
    }

    public function setTags(array $tags): void
    {
    }

    public function setRenamed(array $values): void
    {
    }

    public function setWidened(iterable $tags): void
    {
    }

    /**
     * @param array<Item> $second
     */
    public function merge(array $first, array $second): array
    {
        return [];
    }
}

class MiddleDummy extends ParentDummy
{
    public function getChain(): array
    {
        return [];
    }
}

class GrandChildDummy extends MiddleDummy
{
    public function getItems(): array
    {
        return [];
    }

    public function getChain(): array
    {
        return [];
    }
}

interface ItemsInterface
{
    /**
     * @return list<Item>
     */
    public function getItems(): array;

    /**
     * @param list<Item> $tags
     */
    public function setTags(array $tags): void;
}

interface AInterface
{
    /**
     * @return list<A>
     */
    public function getItems(): array;
}

interface BInterface
{
    /**
     * @return list<B>
     */
    public function getItems(): array;
}

interface SubInterface extends AInterface
{
    public function getItems(): array;
}

interface UndocumentedInterface
{
    public function getItems(): array;
}

class ImplementsInterface implements ItemsInterface
{
    public function getItems(): array
    {
        return [];
    }

    public function setTags(array $tags): void
    {
    }
}

class ChildOfImplementing extends ImplementsInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ImplementsAB implements AInterface, BInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ImplementsBA implements BInterface, AInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ImplementsUndocumentedFirst implements UndocumentedInterface, BInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ImplementsSub implements SubInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ParentA
{
    /**
     * @return list<A>
     */
    public function getItems(): array
    {
        return [];
    }
}

class ParentAImplementsB extends ParentA implements BInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class DescribedParent
{
    /**
     * Returns the items.
     */
    public function getItems(): array
    {
        return [];
    }
}

class DescribedParentAndInterface extends DescribedParent implements ItemsInterface
{
    public function getItems(): array
    {
        return [];
    }

    public function setTags(array $tags): void
    {
    }
}

trait AbstractItemsTrait
{
    /**
     * @return list<Item>
     */
    abstract public function getItems(): array;

    /**
     * @return list<self>
     */
    abstract public function getSelves(): array;

    /**
     * @return self
     */
    abstract public function getNativeSelf(): ?self;

    /**
     * @return list<Item>
     */
    abstract private function getHidden(): array;
}

trait ConcreteItemsTrait
{
    /**
     * @return list<Item>
     */
    public function getItems(): array
    {
        return [];
    }
}

trait UndocumentedTrait
{
    public function getItems(): array
    {
        return [];
    }
}

trait AbstractATrait
{
    /**
     * @return list<A>
     */
    abstract public function getItems(): array;
}

trait AbstractBTrait
{
    /**
     * @return list<B>
     */
    abstract public function getItems(): array;
}

/**
 * @template TItem
 */
trait GenericAbstractTrait
{
    /**
     * @return list<TItem>
     */
    abstract public function getItems(): array;
}

class UndocumentedParent
{
    public function getItems(): array
    {
        return [];
    }
}

class ImplementsAbstractTrait
{
    use AbstractItemsTrait;

    public function getItems(): array
    {
        return [];
    }

    public function getSelves(): array
    {
        return [];
    }

    public function getNativeSelf(): ?self
    {
        return $this;
    }

    public function getHidden(): array
    {
        return [];
    }
}

class OverridesConcreteTrait
{
    use ConcreteItemsTrait;

    public function getItems(): array
    {
        return [];
    }
}

class UndocumentedTraitWithParent extends ParentA
{
    use UndocumentedTrait;
}

class UndocumentedTraitWithInterface implements ItemsInterface
{
    use UndocumentedTrait;

    public function setTags(array $tags): void
    {
    }
}

class ParentVsAbstractTrait extends ParentA
{
    use AbstractBTrait;

    public function getItems(): array
    {
        return [];
    }
}

class InterfaceVsAbstractTrait implements BInterface
{
    use AbstractATrait;

    public function getItems(): array
    {
        return [];
    }
}

class ParentKeptOverAbstractTrait extends UndocumentedParent
{
    use AbstractBTrait;
}

class AfterUseDocBlock
{
    /**
     * @use GenericAbstractTrait<Item>
     */
    use GenericAbstractTrait;

    public function getItems(): array
    {
        return [];
    }
}

class ParentUsingDocumentedTrait
{
    use ConcreteItemsTrait;
}

class ChildOfParentUsingDocumentedTrait extends ParentUsingDocumentedTrait
{
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @template TItem
 */
trait GenericItemsTrait
{
    /**
     * @return list<TItem>
     */
    public function getItems(): array
    {
        return [];
    }
}

class ParentUsingGenericTrait
{
    /**
     * @use GenericItemsTrait<Item>
     */
    use GenericItemsTrait;
}

class ChildOfParentUsingGenericTrait extends ParentUsingGenericTrait
{
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @template TItem
 */
abstract class GenericParent
{
    /**
     * @return list<TItem>
     */
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @extends GenericParent<Item>
 */
class BoundChild extends GenericParent
{
    public function getItems(): array
    {
        return [];
    }
}

class UnboundChild extends GenericParent
{
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @template TValue
 *
 * @extends GenericParent<TValue>
 */
abstract class GenericMiddle extends GenericParent
{
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @extends GenericMiddle<Item>
 */
class BoundLeaf extends GenericMiddle
{
    public function getItems(): array
    {
        return [];
    }
}

/**
 * @template TItem
 */
interface GenericInterface
{
    /**
     * @return list<TItem>
     */
    public function getItems(): array;
}

/**
 * @implements GenericInterface<Item>
 */
class BoundImplementation implements GenericInterface
{
    public function getItems(): array
    {
        return [];
    }
}

class ParentPromoting
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}

class ChildWithoutParameter extends ParentPromoting
{
    public function __construct()
    {
        parent::__construct([]);
    }
}

class ChildRedeclaringPromotedProperty extends ParentPromoting
{
    public array $items = [];

    public function __construct()
    {
        parent::__construct([]);
    }
}

class ChildPromotingAgain extends ParentPromoting
{
    public function __construct(
        public array $items,
    ) {
        parent::__construct($items);
    }
}

class ParentPlain
{
    /**
     * @param list<Item> $items
     */
    public function __construct(array $items)
    {
    }
}

class ChildPromotingOwn extends ParentPlain
{
    public function __construct(
        public array $items,
        public string $name = '',
    ) {
        parent::__construct($items);
    }
}

class ChildPlain extends ParentPlain
{
    public function __construct(array $items)
    {
        parent::__construct($items);
    }
}

class ChildReordered extends ParentPlain
{
    public function __construct(string $name, array $items)
    {
        parent::__construct($items);
    }
}

abstract class AbstractConstructor
{
    /**
     * @param list<Item> $items
     */
    abstract public function __construct(array $items);
}

class ImplementsAbstractConstructor extends AbstractConstructor
{
    public function __construct(array $items)
    {
    }
}

interface ConstructorInterface
{
    /**
     * @param list<Item> $items
     */
    public function __construct(array $items);
}

class ImplementsConstructorInterface implements ConstructorInterface
{
    public function __construct(array $items)
    {
    }
}

class ParentWithVar
{
    /**
     * @var list<Item>
     */
    public array $items = [];
}

class ChildPromotingParentProperty extends ParentWithVar
{
    public function __construct(
        public array $items = [],
    ) {
    }
}

trait PropertyTrait
{
    /**
     * @var list<Item>
     */
    public array $fromTrait = [];
}

class ParentWithProperties
{
    /**
     * @var list<Item>
     */
    public array $typed = [];

    /**
     * @var list<Item>
     */
    public $untyped = [];

    /**
     * @var list<Item>
     */
    protected array $widened = [];

    /**
     * @var list<Item>
     */
    private array $private = [];
}

class ChildWithProperties extends ParentWithProperties
{
    use PropertyTrait;

    public array $typed = [];
    public $untyped = [];
    public array $widened = [];
    public array $private = [];
    public array $fromTrait = [];
}

interface PropertyInterface
{
    /**
     * @var list<Item>
     */
    public array $items { get; }
}

class ImplementsPropertyInterface implements PropertyInterface
{
    public array $items = [];
}

abstract class AbstractProperty
{
    /**
     * @var list<Item>
     */
    abstract public array $items { get; }
}

class ImplementsAbstractProperty extends AbstractProperty
{
    public array $items = [];
}

/**
 * @template TValue
 */
trait EmptyGenericTrait
{
}

class AfterUseDocBlockProperty extends ParentWithProperties
{
    /**
     * @use EmptyGenericTrait<int>
     */
    use EmptyGenericTrait;

    public array $typed = [];
}
