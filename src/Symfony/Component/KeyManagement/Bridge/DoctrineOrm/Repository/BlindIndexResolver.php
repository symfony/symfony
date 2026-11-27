<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Psr\Container\ContainerInterface;
use Symfony\Component\KeyManagement\BlindIndexInterface;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Rewrites criteria on a blind-indexed property into criteria on the column holding its tags.
 *
 * A blind index makes an encrypted value searchable by equality, but the call site has to compute
 * the tag and name the tag column. This resolver does both, from the map of indexed properties that
 * the container builds when it is compiled, so callers can keep naming the source property.
 *
 * The array criteria of findBy() and findOneBy() support equality and IN. Criteria expressions
 * additionally support the comparisons that a tag can answer: equality, inequality, IN, NIN and
 * null. Ordering and partial comparisons are refused, since a tag preserves neither.
 *
 * @experimental
 */
final class BlindIndexResolver
{
    /**
     * @param array<class-string, array<string, array<string, string>>> $fields  The tag column and the index name of each indexed property, by entity class and source property
     * @param ContainerInterface                                        $indexes The blind indexes, keyed by the name they carry
     */
    public function __construct(
        private readonly array $fields,
        private readonly ContainerInterface $indexes,
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function resolve(string $class, #[\SensitiveParameter] array $criteria): array
    {
        foreach ($criteria as $property => $value) {
            if (!isset($this->fields[$class][$property])) {
                continue;
            }

            [$column, $index] = $this->indexOf($class, $property);
            $criteria[$column] = $this->tag($index, $value);

            unset($criteria[$property]);
        }

        return $criteria;
    }

    public function resolveCriteria(string $class, #[\SensitiveParameter] Criteria $criteria): Criteria
    {
        $expression = $criteria->getWhereExpression();

        if (null === $expression || !isset($this->fields[$class])) {
            return $criteria;
        }

        $resolved = clone $criteria;
        $resolved->where($this->resolveExpression($class, $expression));

        return $resolved;
    }

    private function resolveExpression(string $class, Expression $expression): Expression
    {
        if ($expression instanceof Comparison) {
            $property = $expression->getField();

            if (!isset($this->fields[$class][$property])) {
                return $expression;
            }

            [$column, $index] = $this->indexOf($class, $property);

            if (!\in_array($operator = $expression->getOperator(), [Comparison::EQ, Comparison::NEQ, Comparison::IN, Comparison::NIN], true)) {
                throw new LogicException(\sprintf('The "%s" comparison cannot be applied to the blind-indexed property "%s::$%s"; search by equality or by the column holding its tags.', $operator, $class, $property));
            }

            return new Comparison($column, $operator, $this->tag($index, $expression->getValue()->getValue()));
        }

        if ($expression instanceof CompositeExpression) {
            return new CompositeExpression($expression->getType(), array_map(
                fn (Expression $child): Expression => $this->resolveExpression($class, $child),
                $expression->getExpressionList(),
            ));
        }

        return $expression;
    }

    /**
     * @return array{string, BlindIndexInterface}
     */
    private function indexOf(string $class, string $property): array
    {
        $columns = $this->fields[$class][$property];

        if (1 !== \count($columns)) {
            throw new LogicException(\sprintf('The "%s::$%s" property is indexed by several blind indexes ("%s"); search by the column holding the tag of the one you want.', $class, $property, implode('", "', array_keys($columns))));
        }

        $column = array_key_first($columns);
        $index = $this->indexes->get($columns[$column]);

        if (!$index instanceof BlindIndexInterface) {
            throw new LogicException(\sprintf('The blind index "%s" is not an instance of "%s".', $columns[$column], BlindIndexInterface::class));
        }

        return [$column, $index];
    }

    private function tag(BlindIndexInterface $index, #[\SensitiveParameter] mixed $value): array|string|null
    {
        if (null === $value) {
            return null;
        }

        if (\is_array($value)) {
            return array_map(fn (mixed $item): ?string => null === $item ? null : $index->of($this->stringValue($item)), $value);
        }

        return $index->of($this->stringValue($value));
    }

    private function stringValue(#[\SensitiveParameter] mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new LogicException(\sprintf('A blind index is derived from a string, "%s" given.', get_debug_type($value)));
    }
}
