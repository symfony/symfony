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
class StringNotContains extends AbstractStringContains
{
    public const CONTAINS_ERROR = 'abf90f7a-cf90-4db7-872a-f69f05a8db8d';

    protected static $errorNames = [
        self::CONTAINS_ERROR => 'CONTAINS_ERROR',
    ];

    /**
     * @var string
     */
    public $message = 'This contains unexpected value.';
}
