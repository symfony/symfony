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

    protected static $errorNames = [
        self::INVALID_TYPE_ERROR => 'INVALID_TYPE_ERROR',
    ];

    public $message = 'This value should be of type {{ type }}.';
    public $type;

    /**
     * {@inheritdoc}
     *
     * @param string|array $type One ore multiple types to validate against or a set of options.
     */
    public function __construct($type, string $message = null, array $groups = null, $payload = null, array $options = [])
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
    public function getDefaultOption()
    {
        return 'type';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return ['type'];
    }
}
