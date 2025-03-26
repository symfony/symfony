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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a value is a valid date, i.e. its string representation follows the Y-m-d format.
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Date extends Constraint
{
    public const INVALID_FORMAT_ERROR = '69819696-02ac-4a99-9ff0-14e127c4d1bc';
    public const INVALID_DATE_ERROR = '3c184ce5-b31d-4de7-8b76-326da7b2be93';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_DATE_ERROR => 'INVALID_DATE_ERROR',
    ];

    public string $message = 'This value is not a valid date.';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
