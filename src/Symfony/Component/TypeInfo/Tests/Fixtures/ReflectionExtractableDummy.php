<?php

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

final class ReflectionExtractableDummy extends AbstractDummy
{
    public int $builtin;
    public ?int $nullableBuiltin;

    public array $array;
    public ?array $nullableArray;

    public iterable $iterable;
    public ?iterable $nullableIterable;

    public Dummy $class;
    public ?Dummy $nullableClass;

    public self $self;
    public ?self $nullableSelf;

    public parent $parent;
    public ?parent $nullableParent;

    public DummyEnum $enum;
    public ?DummyEnum $nullableEnum;

    public DummyBackedEnum $backedEnum;
    public ?DummyBackedEnum $nullableBackedEnum;

    public int|string $union;
    public \Traversable&\Stringable $intersection;

    public $nothing;

    public function getBuiltin(): int
    {
        return $this->builtin;
    }

    public function getSelf(): self
    {
        return $this->self;
    }

    public function getStatic(): static
    {
        return $this;
    }

    public function getNullableStatic(): ?static
    {
        return null;
    }

    public function getNothing()
    {
        return $this->nothing;
    }

    public function setBuiltin(int $builtin): void
    {
        $this->builtin = $builtin;
    }

    public function setSelf(self $self): void
    {
        $this->self = $self;
    }

    public function setNothing($nothing): void
    {
        $this->nothing = $nothing;
    }

    public function setOptional(?int $optional = null): void
    {
    }
}
