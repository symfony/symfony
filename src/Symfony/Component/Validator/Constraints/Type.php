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
class Type extends Constraint
{
    public const INVALID_TYPE_ERROR = 'ba785a8c-82cb-4283-967c-3cf342181b40';

    protected const ERROR_NAMES = [
        self::INVALID_TYPE_ERROR => 'INVALID_TYPE_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'This value should be of type {{ type }}.';
    public $type;

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

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption(): ?string
    {
        return 'type';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions(): array
    {
        return ['type'];
    }
}
