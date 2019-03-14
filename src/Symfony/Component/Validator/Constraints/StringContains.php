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
class StringContains extends AbstractStringContains
{
    public const NOT_CONTAINS_ERROR = '2e0f55ec-eb23-411d-8480-303ab84fb389';

    protected static $errorNames = [
        self::NOT_CONTAINS_ERROR => 'NOT_CONTAINS_ERROR',
    ];

    /**
     * @var string
     */
    public $message = 'This does not contain expected value.';
}
