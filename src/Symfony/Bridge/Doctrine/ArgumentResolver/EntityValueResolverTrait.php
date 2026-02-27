<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\ArgumentResolver;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Provides common entity resolution logic for both HTTP and Console value resolvers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
trait EntityValueResolverTrait
{
    /**
     * Gets the entity manager for the given class.
     */
    private function getManager(ManagerRegistry $registry, ?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $registry->getManagerForClass($class);
        }

        try {
            $manager = $registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $manager->getMetadataFactory()->isTransient($class) ? null : $manager;
    }

    /**
     * Finds an entity by its identifier.
     *
     * @return false|object|null false when mapping/exclude are set, null when not found, object when found
     */
    private function findById(ObjectManager $manager, MapEntity $options, mixed $id): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        if (false === $id || null === $id) {
            return $id;
        }
        if (\is_array($id) && \in_array(null, $id, true)) {
            return null;
        }

        if ($options->evictCache && $manager instanceof EntityManagerInterface) {
            $cacheProvider = $manager->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($options->class, $id)) {
                $cacheProvider->evictEntity($options->class, $id);
            }
        }

        try {
            return $manager->getRepository($options->class)->find($id);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    /**
     * Finds an entity via expression language.
     */
    private function findViaExpression(?ExpressionLanguage $expressionLanguage, ObjectManager $manager, MapEntity $options, array $variables): object|iterable|null
    {
        if (!$expressionLanguage) {
            throw new \LogicException(\sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', static::class));
        }

        $repository = $manager->getRepository($options->class);
        $variables['repository'] = $repository;

        try {
            return $expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    /**
     * Finds an entity by criteria.
     */
    private function findOneByCriteria(ObjectManager $manager, MapEntity $options, array $criteria): ?object
    {
        try {
            return $manager->getRepository($options->class)->findOneBy($criteria);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    /**
     * Builds criteria from mapping configuration.
     */
    private function buildCriteriaFromMapping(ObjectManager $manager, MapEntity $options, array $mapping, array $values): array
    {
        if (array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude ?? [] as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return [];
        }

        $criteria = [];
        $metadata = null === $options->mapping ? $manager->getClassMetadata($options->class) : false;

        foreach ($mapping as $attribute => $field) {
            if ($metadata && !$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            if (!\array_key_exists($attribute, $values)) {
                continue;
            }

            $criteria[$field] = $values[$attribute];
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }
}
