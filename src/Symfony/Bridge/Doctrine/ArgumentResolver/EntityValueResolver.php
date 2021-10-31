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
    private array $defaultOptions = [
        'object_manager' => null,
        'expr' => null,
        'mapping' => [],
        'exclude' => [],
        'strip_null' => false,
        'id' => null,
        'evict_cache' => false,
        'auto_mapping' => true,
        'attribute_only' => false,
    ];

    public function __construct(
        private ManagerRegistry $registry,
        private ?ExpressionLanguage $language = null,
        array $defaultOptions = [],
    ) {
        $this->defaultOptions = array_merge($this->defaultOptions, $defaultOptions);
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
        if (null === $options['class']) {
            return false;
        }

        if ($options['attribute_only'] && !$options['has_attribute']) {
            return false;
        }

        // Doctrine Entity?
        if (null === $objectManager = $this->getManager($options['object_manager'], $options['class'])) {
            return false;
        }

        return !$objectManager->getMetadataFactory()->isTransient($options['class']);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $options = $this->getOptions($argument);

        $name = $argument->getName();
        $class = $options['class'];

        $errorMessage = null;
        if (null !== $options['expr']) {
            if (null === $object = $this->findViaExpression($class, $request, $options['expr'], $options)) {
                $errorMessage = sprintf('The expression "%s" returned null', $options['expr']);
            }
            // find by identifier?
        } elseif (false === $object = $this->find($class, $request, $options, $name)) {
            // find by criteria
            $object = $this->findOneBy($class, $request, $options);
            if (false === $object) {
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

    private function find(string $class, Request $request, array $options, string $name): false|object|null
    {
        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return false;
        }

        $objectManager = $this->getManager($options['object_manager'], $class);
        if ($options['evict_cache'] && $objectManager instanceof EntityManagerInterface) {
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

    private function getIdentifier(Request $request, array $options, string $name): mixed
    {
        if (\is_array($options['id'])) {
            $id = [];
            foreach ($options['id'] as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = sprintf($field, $name);
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if (null !== $options['id']) {
            $name = $options['id'];
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name);
        }

        if (!$options['id'] && $request->attributes->has('id')) {
            return $request->attributes->get('id');
        }

        return false;
    }

    private function findOneBy(string $class, Request $request, array $options): false|object|null
    {
        if (!$options['mapping']) {
            if (!$options['auto_mapping']) {
                return false;
            }

            $keys = $request->attributes->keys();
            $options['mapping'] = $keys ? array_combine($keys, $keys) : [];
        }

        foreach ($options['exclude'] as $exclude) {
            unset($options['mapping'][$exclude]);
        }

        if (!$options['mapping']) {
            return false;
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if ($options['id'] && null === $request->attributes->get($options['id'])) {
            return false;
        }

        $criteria = [];
        $objectManager = $this->getManager($options['object_manager'], $class);
        $metadata = $objectManager->getClassMetadata($class);

        foreach ($options['mapping'] as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options['strip_null']) {
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

    private function findViaExpression(string $class, Request $request, string $expression, array $options): ?object
    {
        if (null === $this->language) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $this->getManager($options['object_manager'], $class)->getRepository($class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->language->evaluate($expression, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function getOptions(ArgumentMetadata $argument): array
    {
        /** @var ?MapEntity $configuration */
        $configuration = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;

        $argumentClass = $argument->getType();
        if ($argumentClass && !class_exists($argumentClass)) {
            $argumentClass = null;
        }

        if (null === $configuration) {
            return array_merge($this->defaultOptions, [
                'class' => $argumentClass,
                'has_attribute' => false,
            ]);
        }

        return [
            'class' => $configuration->class ?? $argumentClass,
            'object_manager' => $configuration->objectManager ?? $this->defaultOptions['object_manager'],
            'expr' => $configuration->expr ?? $this->defaultOptions['expr'],
            'mapping' => $configuration->mapping ?? $this->defaultOptions['mapping'],
            'exclude' => $configuration->exclude ?? $this->defaultOptions['exclude'],
            'strip_null' => $configuration->stripNull ?? $this->defaultOptions['strip_null'],
            'id' => $configuration->id ?? $this->defaultOptions['id'],
            'evict_cache' => $configuration->evictCache ?? $this->defaultOptions['evict_cache'],
            'has_attribute' => true,
            'auto_mapping' => $this->defaultOptions['auto_mapping'],
            'attribute_only' => $this->defaultOptions['attribute_only'],
        ];
    }
}
