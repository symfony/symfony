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
    use EntityValueResolverTrait;

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

        if (!$manager = $this->getManager($this->registry, $options->objectManager, $options->class)) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            $variables = array_merge($request->attributes->all(), ['request' => $request]);
            if (null === $object = $this->findViaExpression($this->expressionLanguage, $manager, $options, $variables)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
        // find by identifier?
        } elseif (false === $object = $this->findById($manager, $options, $this->getIdentifier($request, $options, $argument))) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager, $argument)) {
                if (!class_exists(NearMissValueResolverException::class)) {
                    return [];
                }

                throw new NearMissValueResolverException(\sprintf('Cannot find mapping for "%s": declare one using either the #[MapEntity] attribute or mapped route parameters.', $options->class));
            }
            $object = $this->findOneByCriteria($manager, $options, $criteria);
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException($options->message ?? (\sprintf('"%s" object not found by "%s".', $options->class, self::class).$message));
        }

        return [$object];
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
        }

        if (!$mapping) {
            return [];
        }

        return $this->buildCriteriaFromMapping($manager, $options, $mapping, $request->attributes->all());
    }
}
