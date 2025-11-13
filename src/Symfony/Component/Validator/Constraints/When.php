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
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Conditionally apply validation constraints based on an expression using the ExpressionLanguage syntax.
 *
 * @see https://symfony.com/doc/current/components/expression_language.html
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class When extends Composite
{
    public string|Expression|\Closure $expression;
    public array|Constraint $constraints = [];
    public array $values = [];
    public array|Constraint $otherwise = [];

    /**
     * @param string|Expression|\Closure(object): bool $expression  The condition to evaluate, either as a closure or using the ExpressionLanguage syntax
     * @param Constraint[]|Constraint|null             $constraints One or multiple constraints that are applied if the expression returns true
     * @param array<string,mixed>|null                 $values      The values of the custom variables used in the expression (defaults to [])
     * @param string[]|null                            $groups
     * @param Constraint[]|Constraint                  $otherwise   One or multiple constraints that are applied if the expression returns false
     */
    #[HasNamedArguments]
    public function __construct(string|Expression|array|\Closure $expression, array|Constraint|null $constraints = null, ?array $values = null, ?array $groups = null, $payload = null, ?array $options = null, array|Constraint $otherwise = [])
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new LogicException(\sprintf('The "symfony/expression-language" component is required to use the "%s" constraint. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        if (\is_array($expression)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($expression, $options ?? []);
        } else {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

                $options['expression'] = $expression;
                if (null !== $constraints) {
                    $options['constraints'] = $constraints;
                }
                $options['otherwise'] = $otherwise;
            } else {
                if (null === $constraints) {
                    throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
                }

                $this->expression = $expression;
                $this->constraints = $constraints;
                $this->otherwise = $otherwise;
            }
        }

        if (!\is_array($options['constraints'] ?? [])) {
            $options['constraints'] = [$options['constraints']];
        }

        if (!\is_array($options['otherwise'] ?? [])) {
            $options['otherwise'] = [$options['otherwise']];
        }

        parent::__construct($options, $groups, $payload);

        $this->values = $values ?? $this->values;
    }

    public function getRequiredOptions(): array
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return ['expression', 'constraints'];
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): array|string
    {
        return ['constraints', 'otherwise'];
    }
}
