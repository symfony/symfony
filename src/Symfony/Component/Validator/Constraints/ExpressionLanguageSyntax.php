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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Andrey Sevastianov <mrpkmail@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ExpressionLanguageSyntax extends Constraint
{
    const EXPRESSION_LANGUAGE_SYNTAX_ERROR = '1766a3f3-ff03-40eb-b053-ab7aa23d988a';

    protected static $errorNames = [
        self::EXPRESSION_LANGUAGE_SYNTAX_ERROR => 'EXPRESSION_LANGUAGE_SYNTAX_ERROR',
    ];

    public $message = 'This value should be a valid expression.';
    public $service;
    public $allowedVariables;

    public function __construct(array $options = null, string $message = null, string $service = null, array $allowedVariables = null, array $groups = null, $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
        $this->allowedVariables = $allowedVariables ?? $this->allowedVariables;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return $this->service ?? static::class.'Validator';
    }
}
