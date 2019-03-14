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
 */
abstract class AbstractStringContains extends Constraint
{
    /**
     * @var string|array|null
     */
    public $text;

    /**
     * @var callable|null
     */
    public $callback;

    public $payload;

    /**
     * @var bool
     */
    public $caseSensitive = false;

    /**
     * @var string
     */
    public $message;

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'text';
    }
}
