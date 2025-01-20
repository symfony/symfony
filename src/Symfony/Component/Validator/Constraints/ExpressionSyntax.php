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

use Symfony\Component\Validator\Constraint;

/**
 * Validates that a value is valid as an ExpressionLanguage expression.
 *
 * @author Andrey Sevastianov <mrpkmail@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ExpressionSyntax extends Constraint
{
    public const EXPRESSION_SYNTAX_ERROR = 'e219aa22-8b11-48ec-81a0-fc07cdb0e13f';

    protected const ERROR_NAMES = [
        self::EXPRESSION_SYNTAX_ERROR => 'EXPRESSION_SYNTAX_ERROR',
    ];

    public string $message = 'This value should be a valid expression.';
    public ?string $service = null;
    public ?array $allowedVariables = null;

    /**
     * @param array<string,mixed>|null $options
     * @param non-empty-string|null    $service          The service used to validate the constraint instead of the default one
     * @param string[]|null            $allowedVariables Restrict the available variables in the expression to these values (defaults to null that allows any variable)
     * @param string[]|null            $groups
     */
    public function __construct(?array $options = null, ?string $message = null, ?string $service = null, ?array $allowedVariables = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
        $this->allowedVariables = $allowedVariables ?? $this->allowedVariables;
    }

    public function validatedBy(): string
    {
        return $this->service ?? static::class.'Validator';
    }
}
