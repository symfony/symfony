<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Loads entities using a {@link QueryBuilder} instance.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ORMQueryBuilderLoader implements EntityLoaderInterface
{
    /**
     * Contains the query builder that builds the query for fetching the
     * entities.
     *
     * This property should only be accessed through queryBuilder.
     */
    private QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function getEntities(): array
    {
        return $this->queryBuilder->getQuery()->execute();
    }

    public function getEntitiesByIds(string $identifier, array $values): array
    {
        if (null !== $this->queryBuilder->getMaxResults() || 0 < (int) $this->queryBuilder->getFirstResult()) {
            // an offset or a limit would apply on results including the where clause with submitted id values
            // that could make invalid choices valid
            $choices = [];
            $metadata = $this->queryBuilder->getEntityManager()->getClassMetadata(current($this->queryBuilder->getRootEntities()));

            foreach ($this->getEntities() as $entity) {
                if (\in_array((string) current($metadata->getIdentifierValues($entity)), $values, true)) {
                    $choices[] = $entity;
                }
            }

            return $choices;
        }

        $qb = clone $this->queryBuilder;
        $alias = current($qb->getRootAliases());
        $parameter = 'ORMQueryBuilderLoader_getEntitiesByIds_'.$identifier;
        $parameter = str_replace('.', '_', $parameter);
        $where = $qb->expr()->in($alias.'.'.$identifier, ':'.$parameter);

        // Guess type
        $entity = current($qb->getRootEntities());
        $metadata = $qb->getEntityManager()->getClassMetadata($entity);
        if (\in_array($type = $metadata->getTypeOfField($identifier), ['integer', 'bigint', 'smallint'])) {
            $parameterType = class_exists(ArrayParameterType::class) ? ArrayParameterType::INTEGER : Connection::PARAM_INT_ARRAY;

            // Filter out non-integer values (e.g. ""). If we don't, some
            // databases such as PostgreSQL fail.
            $values = array_values(array_filter($values, fn ($v) => (string) $v === (string) (int) $v || ctype_digit($v)));
        } elseif (\in_array($type, ['ulid', 'uuid', 'guid'])) {
            $parameterType = class_exists(ArrayParameterType::class) ? ArrayParameterType::STRING : Connection::PARAM_STR_ARRAY;

            // Like above, but we just filter out empty strings.
            $values = array_values(array_filter($values, fn ($v) => '' !== (string) $v));

            // Convert values into right type
            if (Type::hasType($type)) {
                $doctrineType = Type::getType($type);
                $platform = $qb->getEntityManager()->getConnection()->getDatabasePlatform();
                foreach ($values as &$value) {
                    try {
                        $value = $doctrineType->convertToDatabaseValue($value, $platform);
                    } catch (ConversionException $e) {
                        throw new TransformationFailedException(sprintf('Failed to transform "%s" into "%s".', $value, $type), 0, $e);
                    }
                }
                unset($value);
            }
        } else {
            $parameterType = class_exists(ArrayParameterType::class) ? ArrayParameterType::STRING : Connection::PARAM_STR_ARRAY;
        }
        if (!$values) {
            return [];
        }

        return $qb->andWhere($where)
                  ->getQuery()
                  ->setParameter($parameter, $values, $parameterType)
                  ->getResult();
    }
}
