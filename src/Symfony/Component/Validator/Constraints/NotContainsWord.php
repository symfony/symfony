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
class NotContainsWord extends AbstractStringContains
{
    public const CONTAINS_WORD_ERROR = '9e1b8912-372a-4f6d-8312-d3af2c99d788';

    protected static $errorNames = [
        self::CONTAINS_WORD_ERROR => 'CONTAINS_WORD_ERROR',
    ];

    /**
     * @var string
     */
    public $message = 'This value contains an unexpected word.';
}
