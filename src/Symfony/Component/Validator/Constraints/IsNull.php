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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class IsNull extends Constraint
{
    public const NOT_NULL_ERROR = '60d2f30b-8cfa-4372-b155-9656634de120';

    protected static $errorNames = [
        self::NOT_NULL_ERROR => 'NOT_NULL_ERROR',
    ];

    public $message = 'This value should be null.';

    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
