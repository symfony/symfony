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

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class ContainsWord extends AbstractStringContains
{
    public const NOT_CONTAINS_WORD_ERROR = 'daca54aa-581f-4215-b0f0-34e0f8f765e3';

    protected static $errorNames = [
        self::NOT_CONTAINS_WORD_ERROR => 'NOT_CONTAINS_WORD_ERROR',
    ];

    /**
     * @var string
     */
    public $message = 'This value does not contain expected word.';
}
