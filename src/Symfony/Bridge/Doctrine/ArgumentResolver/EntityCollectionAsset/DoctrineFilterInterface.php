<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
