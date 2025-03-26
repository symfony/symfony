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
 * Validates that a value is a valid "datetime" according to a given format.
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DateTime extends Constraint
{
    public const INVALID_FORMAT_ERROR = '1a9da513-2640-4f84-9b6a-4d99dcddc628';
    public const INVALID_DATE_ERROR = 'd52afa47-620d-4d99-9f08-f4d85b36e33c';
    public const INVALID_TIME_ERROR = '5e797c9d-74f7-4098-baa3-94390c447b27';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_DATE_ERROR => 'INVALID_DATE_ERROR',
        self::INVALID_TIME_ERROR => 'INVALID_TIME_ERROR',
    ];

    public string $format = 'Y-m-d H:i:s';
    public string $message = 'This value is not a valid datetime.';

    /**
     * @param non-empty-string|array<string,mixed>|null $format  The datetime format to match (defaults to 'Y-m-d H:i:s')
     * @param string[]|null                             $groups
     * @param array<string,mixed>|null                  $options
     */
    #[HasNamedArguments]
    public function __construct(string|array|null $format = null, ?string $message = null, ?array $groups = null, mixed $payload = null, ?array $options = null)
    {
        if (\is_array($format)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($format, $options ?? []);
        } elseif (null !== $format) {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            } else {
                $options = [];
            }

            $options['value'] = $format;
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getDefaultOption(): ?string
    {
        return 'format';
    }
}
