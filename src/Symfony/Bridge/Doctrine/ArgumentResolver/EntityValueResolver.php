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
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;
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
        /** @var array<class-string, class-string> */
        private readonly array $typeAliases = [],
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->withDefaults($this->defaults, $argument->getType());

        if (!$options->class || $options->disabled) {
            return [];
        }

        $options->class = $this->typeAliases[$options->class] ?? $options->class;

        if (!$manager = $this->getManager($options->objectManager, $options->class)) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($manager, $request, $options)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
        // find by identifier?
        } elseif (false === $object = $this->find($manager, $request, $options, $argument)) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager, $argument)) {
                if (!class_exists(NearMissValueResolverException::class)) {
                    return [];
                }

                throw new NearMissValueResolverException(\sprintf('Cannot find mapping for "%s": declare one using either the #[MapEntity] attribute or mapped route parameters.', $options->class));
            }
            try {
                $object = $manager->getRepository($options->class)->findOneBy($criteria);
            } catch (NoResultException|ConversionException) {
                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException($options->message ?? (\sprintf('"%s" object not found by "%s".', $options->class, self::class).$message));
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

    private function find(ObjectManager $manager, Request $request, MapEntity $options, ArgumentMetadata $argument): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $argument);
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

    private function getIdentifier(Request $request, MapEntity $options, ArgumentMetadata $argument): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = \sprintf($field, $argument->getName());
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if ($options->id) {
            return $request->attributes->get($options->id) ?? ($options->stripNull ? false : null);
        }

        $name = $argument->getName();

        if ($request->attributes->has($name)) {
            if (\is_array($id = $request->attributes->get($name))) {
                return false;
            }

            foreach ($request->attributes->get('_route_mapping') ?? [] as $parameter => $attribute) {
                if ($name === $attribute) {
                    $options->mapping = [$name => $parameter];

                    return false;
                }
            }

            return $id ?? ($options->stripNull ? false : null);
        }

        if ($request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ObjectManager $manager, ArgumentMetadata $argument): array
    {
        if (!($mapping = $options->mapping) && \is_array($criteria = $request->attributes->get($argument->getName()))) {
            foreach ($options->exclude as $exclude) {
                unset($criteria[$exclude]);
            }

            if ($options->stripNull) {
                $criteria = array_filter($criteria, static fn ($value) => null !== $value);
            }

            return $criteria;
        } elseif (null === $mapping) {
            trigger_deprecation('symfony/doctrine-bridge', '7.1', 'Relying on auto-mapping for Doctrine entities is deprecated for argument $%s of "%s": declare the mapping using either the #[MapEntity] attribute or mapped route parameters.', $argument->getName(), method_exists($argument, 'getControllerName') ? $argument->getControllerName() : 'n/a');
            $mapping = $request->attributes->keys();
        }

        if ($mapping && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
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

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(ObjectManager $manager, Request $request, MapEntity $options): object|iterable|null
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(\sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $manager->getRepository($options->class);
        $variables = array_merge($request->attributes->all(), [
            'repository' => $repository,
            'request' => $request,
        ]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }
}
