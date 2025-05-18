<?php

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\Attribute;

use Attribute;
use Doctrine\Common\Collections\Order;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset\DoctrineFilterInterface;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
class MapEntityCollection extends ValueResolver
{
    /**
     * @param class-string $class
     * @param array<string, string> $queryMapping
     * @param array<string, mixed> $doctrineParameters
     * @param class-string<DoctrineFilterInterface>[] $filters
     * @param array<string, Order> $defaultOrdering
     */
    public function __construct(
        private readonly string $class,
        private readonly ?string $queryString = null,
        private readonly array $queryMapping = [],
        private readonly array $doctrineParameters = [],
        private readonly array $filters = [],
        private readonly array $defaultOrdering = [],
        private readonly bool $returnPaginator = true,
        private readonly ?NameConverterInterface $nameConverter = null,
    )
    {
        parent::__construct(EntityCollectionResolver::class);
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    /**
     * @return array<string, string>
     */
    public function getQueryMapping(): array
    {
        return $this->queryMapping;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDoctrineParameters(): array
    {
        return $this->doctrineParameters;
    }

    /**
     * @return class-string<DoctrineFilterInterface>[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, Order>
     */
    public function getDefaultOrdering(): array
    {
        return $this->defaultOrdering;
    }

    public function isReturnPaginator(): bool
    {
        return $this->returnPaginator;
    }

    public function getNameConverter(): ?NameConverterInterface
    {
        return $this->nameConverter;
    }
}
