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

trigger_deprecation('symfony/validator', '6.1', 'The "%s" constraint is deprecated since symfony 6.1, use "ExpressionSyntax" instead.', ExpressionLanguageSyntax::class);

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Andrey Sevastianov <mrpkmail@gmail.com>
 *
 * @deprecated since symfony 6.1, use ExpressionSyntax instead
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ExpressionLanguageSyntax extends Constraint
{
    public const EXPRESSION_LANGUAGE_SYNTAX_ERROR = '1766a3f3-ff03-40eb-b053-ab7aa23d988a';

    protected const ERROR_NAMES = [
        self::EXPRESSION_LANGUAGE_SYNTAX_ERROR => 'EXPRESSION_LANGUAGE_SYNTAX_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'This value should be a valid expression.';
    public $service;
    public $allowedVariables;

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
