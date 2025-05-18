<?php

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset;

use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Attribute\MapEntityCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface DoctrineFilterInterface
{
    public function applyFilter(
        QueryBuilder $queryBuilder,
        MapEntityCollection $attribute,
        Request $request,
        ?object $queryStringObject = null,
    ): void;
}
