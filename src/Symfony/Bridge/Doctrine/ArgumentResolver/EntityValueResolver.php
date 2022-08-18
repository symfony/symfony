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
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Yields the entity matching the criteria provided in the route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class EntityValueResolver implements ArgumentValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private ?ExpressionLanguage $expressionLanguage = null,
        private MapEntity $defaults = new MapEntity(),
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if (!$this->registry->getManagerNames()) {
            return false;
        }

        $options = $this->getOptions($argument);
        if (!$options->class || $options->disabled) {
            return false;
        }

        // Doctrine Entity?
        if (!$objectManager = $this->getManager($options->objectManager, $options->class)) {
            return false;
        }

        return !$objectManager->getMetadataFactory()->isTransient($options->class);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $options = $this->getOptions($argument);
        $name = $argument->getName();
        $class = $options->class;

        $errorMessage = null;
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($class, $request, $options->expr, $options)) {
                $errorMessage = sprintf('The expression "%s" returned null', $options->expr);
            }
        // find by identifier?
        } elseif (false === $object = $this->find($class, $request, $options, $name)) {
            // find by criteria
            if (false === $object = $this->findOneBy($class, $request, $options)) {
                if (!$argument->isNullable()) {
                    throw new \LogicException(sprintf('Unable to guess how to get a Doctrine instance from the request information for parameter "%s".', $name));
                }

                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            $message = sprintf('"%s" object not found by the "%s" Argument Resolver.', $class, self::class);
            if ($errorMessage) {
                $message .= ' '.$errorMessage;
            }

            throw new NotFoundHttpException($message);
        }

        return [$object];
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        if (!isset($this->registry->getManagerNames()[$name])) {
            return null;
        }

        try {
            return $this->registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function find(string $class, Request $request, MapEntity $options, string $name): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return $id;
        }

        $objectManager = $this->getManager($options->objectManager, $class);
        if ($options->evictCache && $objectManager instanceof EntityManagerInterface) {
            $cacheProvider = $objectManager->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($class, $id)) {
                $cacheProvider->evictEntity($class, $id);
            }
        }

        try {
            return $objectManager->getRepository($class)->find($id);
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
            return $request->attributes->get($name);
        }

        if (!$options->id && $request->attributes->has('id')) {
            return $request->attributes->get('id');
        }

        return false;
    }

    private function findOneBy(string $class, Request $request, MapEntity $options): false|object|null
    {
        if (null === $mapping = $options->mapping) {
            $keys = $request->attributes->keys();
            $mapping = $keys ? array_combine($keys, $keys) : [];
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return false;
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (\is_string($options->id) && null === $request->attributes->get($options->id)) {
            return false;
        }

        $criteria = [];
        $objectManager = $this->getManager($options->objectManager, $class);
        $metadata = $objectManager->getClassMetadata($class);

        foreach ($mapping as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        if (!$criteria) {
            return false;
        }

        try {
            return $objectManager->getRepository($class)->findOneBy($criteria);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function findViaExpression(string $class, Request $request, string $expression, MapEntity $options): ?object
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $this->getManager($options->objectManager, $class)->getRepository($class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->expressionLanguage->evaluate($expression, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function getOptions(ArgumentMetadata $argument): MapEntity
    {
        /** @var MapEntity $options */
        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? $this->defaults;

        return $options->withDefaults($this->defaults, $argument->getType());
    }
}
