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
 * @author Yevgeniy Zholkevskiy <zhenya.zholkevskiy@gmail.com>
 */
class Unique extends Constraint
{
    public const IS_NOT_UNIQUE = '7911c98d-b845-4da0-94b7-a8dac36bc55a';

    protected static $errorNames = [
        self::IS_NOT_UNIQUE => 'IS_NOT_UNIQUE',
    ];

    public $message = 'This collection should contain only unique elements.';
}
