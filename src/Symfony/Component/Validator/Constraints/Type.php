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

    public function __construct(string|array|null $type, string $message = null, array $groups = null, mixed $payload = null, array $options = [])
    {
        if (\is_array($type) && \is_string(key($type))) {
            $options = array_merge($type, $options);
        } elseif (null !== $type) {
            $options['value'] = $type;
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
