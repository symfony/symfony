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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Yields the entity matching the criteria provided in the route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class EntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private ?ExpressionLanguage $expressionLanguage = null,
        private MapEntity $defaults = new MapEntity(),
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        $options = $this->getMapEntityAttribute($argument);
        if (!$options->class || $options->disabled) {
            return [];
        }
        if (!$manager = $this->getManager($options->objectManager, $options->class)) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($manager, $request, $options)) {
                $message = sprintf(' The expression "%s" returned null.', $options->expr);
            }
        // find by identifier?
        } elseif (false === $object = $this->find($manager, $request, $options, $argument->getName())) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager)) {
                return [];
            }
            try {
                $object = $manager->getRepository($options->class)->findOneBy($criteria);
            } catch (NoResultException|ConversionException) {
                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException(sprintf('"%s" object not found by "%s".', $options->class, self::class).$message);
        }

        return [$object];
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        try {
            $manager = $this->registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $manager->getMetadataFactory()->isTransient($class) ? null : $manager;
    }

    private function find(ObjectManager $manager, Request $request, MapEntity $options, string $name): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return $id;
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

    private function getIdentifier(Request $request, MapEntity $options, string $name): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = sprintf($field, $name);
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if (null !== $options->id) {
            $name = $options->id;
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name) ?? ($options->stripNull ? false : null);
        }

        if (!$options->id && $request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ObjectManager $manager): array
    {
        if (null === $mapping = $options->mapping) {
            $mapping = $request->attributes->keys();
        }

        if ($mapping && \is_array($mapping) && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return [];
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (\is_string($options->id) && null === $request->attributes->get($options->id)) {
            return [];
        }

        $criteria = [];
        $metadata = $manager->getClassMetadata($options->class);

        foreach ($mapping as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(ObjectManager $manager, Request $request, MapEntity $options): ?object
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $manager->getRepository($options->class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function getMapEntityAttribute(ArgumentMetadata $argument): MapEntity
    {
        /** @var MapEntity $options */
        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? $this->defaults;

        return $options->withDefaults($this->defaults, $argument->getType());
    }
}
