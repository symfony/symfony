<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Collects the expressions that the attributes of controllers hold, plus the ones listed in the configuration, for each expression language that evaluates them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class ExpressionCollector
{
    /**
     * @var list<array{object, string}>
     */
    private array $instances;

    /**
     * @var list<array{string, \Throwable}>
     */
    private array $errors = [];

    /**
     * @param list<class-string>                                                                $controllers
     * @param array<string, array<class-string, array<string, array{bool, list<string>|null}>>> $attributes  The properties of controller attributes that hold expressions, mapped to whether their strings are expressions too and to the variables these expressions can read, indexed by the id of the expression language that evaluates them
     * @param array<string, array<string, array{list<string>|null, list<string>}>>              $expressions More expressions, mapped to the variables they can read and to where they are listed, indexed by the id of the expression language that evaluates them
     */
    public function __construct(
        private array $controllers,
        private array $attributes = [],
        private array $expressions = [],
    ) {
    }

    /**
     * Yields the expressions that the attributes of controllers hold for an expression language.
     *
     * @param string $expressionLanguageId The service id of the expression language
     *
     * @return iterable<CollectedExpression>
     */
    public function getAttributeExpressions(string $expressionLanguageId): iterable
    {
        foreach ($this->attributes[$expressionLanguageId] ?? [] as $class => $properties) {
            foreach ($this->getInstances() as [$instance, $location]) {
                if (!$instance instanceof $class) {
                    continue;
                }

                $values = get_object_vars($instance);
                foreach ($properties as $property => [$stringsAreExpressions, $variables]) {
                    $source = \sprintf('the "%s" option of #[%s] on %s', $property, self::getShortName($instance::class), $location);
                    $value = $values[$property] ?? null;

                    if ($stringsAreExpressions && \is_string($value)) {
                        yield new CollectedExpression($value, $variables, $source);

                        continue;
                    }

                    foreach (self::findExpressions($value) as $expression) {
                        yield new CollectedExpression($expression, $variables, $source);
                    }
                }
            }
        }
    }

    /**
     * Yields the expressions listed in the configuration for an expression language.
     *
     * @param string $expressionLanguageId The service id of the expression language
     *
     * @return iterable<CollectedExpression>
     */
    public function getListedExpressions(string $expressionLanguageId): iterable
    {
        foreach ($this->expressions[$expressionLanguageId] ?? [] as $expression => [$variables, $sources]) {
            yield new CollectedExpression((string) $expression, $variables, $sources ? implode(' and ', $sources) : \sprintf('the expressions listed for the "%s" service', $expressionLanguageId));
        }
    }

    /**
     * Yields the errors thrown when instantiating the attributes of controllers that hold expressions, keyed by where these attributes are.
     *
     * @return iterable<string, \Throwable>
     */
    public function getErrors(): iterable
    {
        $this->getInstances();

        foreach ($this->errors as [$source, $error]) {
            yield $source => $error;
        }
    }

    /**
     * @return list<array{object, string}>
     */
    private function getInstances(): array
    {
        if (isset($this->instances)) {
            return $this->instances;
        }

        $this->instances = [];

        if (!$classes = array_keys(array_merge(...array_values($this->attributes)))) {
            return [];
        }

        foreach ($this->controllers as $controller) {
            $r = new \ReflectionClass($controller);
            $reflectors = [[$r->getAttributes(), \sprintf('"%s"', $controller)]];

            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $reflectors[] = [$method->getAttributes(), $location = \sprintf('"%s::%s()"', $controller, $method->name)];

                foreach ($method->getParameters() as $parameter) {
                    $reflectors[] = [$parameter->getAttributes(), \sprintf('the "$%s" argument of %s', $parameter->name, $location)];
                }
            }

            foreach ($reflectors as [$attributes, $location]) {
                foreach ($attributes as $attribute) {
                    if (!self::holdsExpressions($attribute->getName(), $classes)) {
                        continue;
                    }

                    try {
                        $this->instances[] = [$attribute->newInstance(), $location];
                    } catch (\Throwable $e) {
                        $this->errors[] = [\sprintf('#[%s] on %s', self::getShortName($attribute->getName()), $location), $e];
                    }
                }
            }
        }

        return $this->instances;
    }

    /**
     * @param list<class-string> $classes
     */
    private static function holdsExpressions(string $attribute, array $classes): bool
    {
        // attributes whose class doesn't exist are ignored when the controller runs too
        if (!class_exists($attribute)) {
            return false;
        }

        foreach ($classes as $class) {
            if (is_a($attribute, $class, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<Expression>
     */
    private static function findExpressions(mixed $value): iterable
    {
        if ($value instanceof Expression) {
            yield $value;
        } elseif (\is_array($value)) {
            foreach ($value as $item) {
                yield from self::findExpressions($item);
            }
        }
    }

    private static function getShortName(string $class): string
    {
        return substr(strrchr('\\'.$class, '\\'), 1);
    }
}
