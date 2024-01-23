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
class Time extends Constraint
{
    public const INVALID_FORMAT_ERROR = '9d27b2bb-f755-4fbf-b725-39b1edbdebdf';
    public const INVALID_TIME_ERROR = '8532f9e1-84b2-4d67-8989-0818bc38533b';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_TIME_ERROR => 'INVALID_TIME_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $withSeconds = true;
    public $message = 'This value is not a valid time.';

    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
        ?bool $withSeconds = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->withSeconds = $withSeconds ?? $this->withSeconds;
        $this->message = $message ?? $this->message;
    }
}
