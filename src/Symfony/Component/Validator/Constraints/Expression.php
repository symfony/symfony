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

use Symfony\Component\ExpressionLanguage\Expression as ExpressionObject;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Expression extends Constraint
{
    public const EXPRESSION_FAILED_ERROR = '6b3befbc-2f01-4ddf-be21-b57898905284';

    protected const ERROR_NAMES = [
        self::EXPRESSION_FAILED_ERROR => 'EXPRESSION_FAILED_ERROR',
    ];

    public string $message = 'This value is not valid.';
    public string|ExpressionObject|null $expression = null;
    public array $values = [];
    public bool $negate = true;

    public function __construct(
        string|ExpressionObject|array|null $expression,
        string $message = null,
        array $values = null,
        array $groups = null,
        mixed $payload = null,
        array $options = [],
        bool $negate = null,
    ) {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new LogicException(sprintf('The "symfony/expression-language" component is required to use the "%s" constraint. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        if (\is_array($expression)) {
            $options = array_merge($expression, $options);
        } else {
            $options['value'] = $expression;
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->values = $values ?? $this->values;
        $this->negate = $negate ?? $this->negate;
    }

    public function getDefaultOption(): ?string
    {
        return 'expression';
    }

    public function getRequiredOptions(): array
    {
        return ['expression'];
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    public function validatedBy(): string
    {
        return 'validator.expression';
    }
}
