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
 * Validates that a value is of a specific data type.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Type extends Constraint
{
    public const INVALID_TYPE_ERROR = 'ba785a8c-82cb-4283-967c-3cf342181b40';

    protected const ERROR_NAMES = [
        self::INVALID_TYPE_ERROR => 'INVALID_TYPE_ERROR',
    ];

    public string $message = 'This value should be of type {{ type }}.';
    public string|array|null $type = null;

    /**
     * @param string|string[]|array<string,mixed>|null $type    The type(s) to enforce on the value
     * @param string[]|null                            $groups
     * @param array<string,mixed>|null                 $options
     */
    #[HasNamedArguments]
    public function __construct(string|array|null $type, ?string $message = null, ?array $groups = null, mixed $payload = null, ?array $options = null)
    {
        if (\is_array($type) && \is_string(key($type))) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($type, $options ?? []);
        } elseif (null !== $type) {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            } else {
                $options = [];
            }

            $options['value'] = $type;
        } elseif (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getDefaultOption(): ?string
    {
        return 'type';
    }

    public function getRequiredOptions(): array
    {
        return ['type'];
    }
}
