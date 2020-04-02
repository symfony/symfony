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

use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @Annotation
 */
class Uid extends Constraint
{
    const INVALID_UID_ERROR = '34fd666d-3eb6-4f82-9965-fa7decd445d0';

    protected static $errorNames = [
        self::INVALID_UID_ERROR => 'INVALID_UID_ERROR',
    ];

    public const UUID_V1 = 'UUID_V1';
    public const UUID_V3 = 'UUID_V3';
    public const UUID_V4 = 'UUID_V4';
    public const UUID_V5 = 'UUID_V5';
    public const UUID_V6 = 'UUID_V6';
    public const ULID = 'ULID';

    /**
     * @var string[]
     *
     * @internal
     */
    public static $availableTypes = [
        self::UUID_V1,
        self::UUID_V3,
        self::UUID_V4,
        self::UUID_V5,
        self::UUID_V6,
        self::ULID,
    ];

    public $message = 'This value is not valid.';

    /**
     * @var int[]
     */
    public $types = [
        self::UUID_V1,
        self::UUID_V3,
        self::UUID_V4,
        self::UUID_V5,
        self::UUID_V6,
        self::ULID,
    ];

    public $normalizer;

    public function __construct($options = null)
    {
        if (!class_exists(AbstractUid::class)) {
            throw new LogicException('Unable to use the UID Constraint, as Symfony Uid component is not installed.');
        }

        if (\is_array($options) && \array_key_exists('types', $options)) {
            if (!\is_array($options['types'])) {
                throw new InvalidArgumentException('The "types" parameter should be an array.');
            }
            array_map(function ($value) {
                if (!\in_array($value, self::$availableTypes, true)) {
                    throw new InvalidArgumentException('The "types" parameter is not valid.');
                }
            }, $options['types']);
        }

        parent::__construct($options);

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }

    public function getDefaultOption()
    {
        return 'types';
    }
}
