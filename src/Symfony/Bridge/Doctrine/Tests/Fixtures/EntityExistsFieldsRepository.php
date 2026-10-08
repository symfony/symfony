<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Scopes its query builder, with a parameter, as a multi-tenant repository would.
 */
class EntityExistsFieldsRepository extends EntityRepository
{
    public static ?int $maxId = null;

    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $queryBuilder = parent::createQueryBuilder($alias, $indexBy);

        if (null !== self::$maxId) {
            $queryBuilder->andWhere($alias.'.id <= :maxId')->setParameter('maxId', self::$maxId);
        }

        return $queryBuilder;
    }
}
