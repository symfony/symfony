<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Conditionally apply validation constraints based on an expression using the ExpressionLanguage syntax.
 *
 * @see https://symfony.com/doc/current/components/expression_language.html
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class When extends Composite
{
    public string|Expression $expression;
    public array|Constraint $constraints = [];
    public array $values = [];

    /**
     * @param string|Expression|array<string,mixed> $expression  The condition to evaluate, written with the ExpressionLanguage syntax
     * @param Constraint[]|Constraint|null          $constraints One or multiple constraints that are applied if the expression returns true
     * @param array<string,mixed>|null              $values      The values of the custom variables used in the expression (defaults to [])
     * @param string[]|null                         $groups
     * @param array<string,mixed>                   $options
     */
    public function __construct(string|Expression|array $expression, array|Constraint|null $constraints = null, ?array $values = null, ?array $groups = null, $payload = null, array $options = [])
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new LogicException(sprintf('The "symfony/expression-language" component is required to use the "%s" constraint. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        if (\is_array($expression)) {
            $options = array_merge($expression, $options);
        } else {
            $options['expression'] = $expression;
            $options['constraints'] = $constraints;
        }

        if (isset($options['constraints']) && !\is_array($options['constraints'])) {
            $options['constraints'] = [$options['constraints']];
        }

        if (null !== $groups) {
            $options['groups'] = $groups;
        }

        if (null !== $payload) {
            $options['payload'] = $payload;
        }

        parent::__construct($options);

        $this->values = $values ?? $this->values;
    }

    public function getRequiredOptions(): array
    {
        return ['expression', 'constraints'];
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
