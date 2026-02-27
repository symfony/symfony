<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\ArgumentResolver\Console;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolverTrait;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Console\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\String\UnicodeString;

/**
 * Resolves a Command parameter holding the #[MapEntity] attribute to an Entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
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

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if (!Argument::tryFrom($member->getMember()) && !Option::tryFrom($member->getMember())) {
            return [];
        }

        $type = $member->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $inputName = $member->getInputName();

        if ($input->hasArgument($inputName) && \is_object($input->getArgument($inputName))) {
            return [];
        }

        // #[MapEntity] is optional
        $attribute = $member->getAttribute(MapEntity::class) ?? $this->defaults;

        $options = $attribute->withDefaults($this->defaults, $type->getName());

        if (!$options->class) {
            return [];
        }

        $options->class = $this->typeAliases[$options->class] ?? $options->class;

        if (!$manager = $this->getManager($this->registry, $options->objectManager, $options->class)) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            $variables = array_merge($input->getArguments(), ['input' => $input]);
            if (null === $object = $this->findViaExpression($this->expressionLanguage, $manager, $options, $variables)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
        } elseif (false === $object = $this->findById($manager, $options, $this->getIdentifier($inputName, $input, $options, $member))) {
            if (!$criteria = $this->getCriteria($inputName, $input, $options, $manager, $member)) {
                throw new NearMissValueResolverException(\sprintf('Cannot find mapping for "%s": use the #[MapEntity] attribute to configure entity resolution.', $options->class));
            }
            $object = $this->findOneByCriteria($manager, $options, $criteria);
        }

        if (null === $object && !$member->isNullable()) {
            throw new RuntimeException($options->message ?? \sprintf('"%s" object not found by "%s".%s', $options->class, self::class, $message));
        }

        return [$object];
    }

    private function getIdentifier(string $argumentName, InputInterface $input, MapEntity $options, ReflectionMember $member): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                if (str_contains($field, '%s')) {
                    $field = \sprintf($field, $argumentName);
                }

                $fieldName = (new UnicodeString($field))->kebab()->toString();

                if (!$input->hasArgument($fieldName)) {
                    return $options->stripNull ? false : null;
                }

                $id[$field] = $input->getArgument($fieldName);
            }

            return $id;
        }

        if ($options->id) {
            $idName = (new UnicodeString($options->id))->kebab()->toString();

            return $input->hasArgument($idName)
                ? $input->getArgument($idName)
                : ($options->stripNull ? false : null);
        }

        if ($input->hasArgument($argumentName)) {
            $value = $input->getArgument($argumentName);
            if (\is_array($value)) {
                return false;
            }

            return $value ?? ($options->stripNull ? false : null);
        }

        if ($input->hasArgument('id')) {
            return $input->getArgument('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(string $argumentName, InputInterface $input, MapEntity $options, ObjectManager $manager, ReflectionMember $member): array
    {
        $mapping = $options->mapping;

        if (!$mapping && $input->hasArgument($argumentName) && \is_array($criteria = $input->getArgument($argumentName))) {
            foreach ($options->exclude ?? [] as $exclude) {
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

        if (array_is_list($mapping)) {
            /** @var list<string> $list */
            $list = $mapping;
            $mapping = array_combine($list, $list);
        }

        $values = [];
        foreach (array_keys($mapping) as $attribute) {
            $attributeName = (new UnicodeString($attribute))->kebab()->toString();
            if ($input->hasArgument($attributeName)) {
                $values[$attribute] = $input->getArgument($attributeName);
            }
        }

        return $this->buildCriteriaFromMapping($manager, $options, $mapping, $values);
    }
}
