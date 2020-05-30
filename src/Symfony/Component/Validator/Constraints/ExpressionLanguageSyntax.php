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
class ExpressionLanguageSyntax extends Constraint
{
    const EXPRESSION_LANGUAGE_SYNTAX_ERROR = '1766a3f3-ff03-40eb-b053-ab7aa23d988a';

    protected static $errorNames = [
        self::EXPRESSION_LANGUAGE_SYNTAX_ERROR => 'EXPRESSION_LANGUAGE_SYNTAX_ERROR',
    ];

    public $message = 'This value should be a valid expression.';
    public $service;
    public $allowedVariables = null;

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return $this->service ?? static::class.'Validator';
    }
}
