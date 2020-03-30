<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @Annotation
 */
class Uid extends Constraint
{

    const INVALID_UID_ERROR  = '34fd666d-3eb6-4f82-9965-fa7decd445d0';
    const INVALID_ULID_ERROR = '043e05d2-9dbe-4fea-bde7-e697eca5f644';
    const INVALID_UUID_ERROR = '83448083-aac9-4f70-9fcc-505fb32fa6b4';
    const INVALID_VERSIONS_ERROR = '2cfab2d7-9d3d-4f24-b5c5-6d07d8526cc0';

    protected static $errorNames = [
        self::INVALID_UID_ERROR => 'INVALID_UID_ERROR',
        self::INVALID_ULID_ERROR => 'INVALID_ULID_ERROR',
        self::INVALID_UUID_ERROR => 'INVALID_UUID_ERROR',
        self::INVALID_VERSIONS_ERROR => 'INVALID_VERSIONS_ERROR',
    ];

    public const TYPE_UUID = 'UUID';
    public const TYPE_ULID = 'ULID';

    /**
     * @var string[]
     *
     * @internal
     */
    public static $availableTypes = [
        self::TYPE_UUID,
        self::TYPE_ULID,
    ];

    public const V1 = 1;
    public const V3 = 3;
    public const V4 = 4;
    public const V5 = 5;
    public const V6 = 6;

    /**
     * @var int[]
     *
     * @internal
     */
    public static $availableVersions = [
        self::V1,
        self::V3,
        self::V4,
        self::V5,
        self::V6,
    ];

    public $message = 'This is neither a valid UUID nor ULID.';
    public $ulidMessage = 'This is not a valid ULID.';
    public $uuidMessage = 'This is not a valid UUID.';
    public $versionsMessage = 'This UUID does not match expected versions.';

    /**
     * @var string
     */
    public $types = [
        self::TYPE_ULID,
        self::TYPE_UUID,
    ];

    /**
     * @var int[]
     */
    public $versions = [
        self::V1,
        self::V3,
        self::V4,
        self::V5,
        self::V6,
    ];

    public $normalizer;

    public function __construct($options = null)
    {

        if (\is_array($options) && \array_key_exists('types', $options)) {
            if (!\is_array($options['types'])) {
                throw new InvalidArgumentException('The "types" parameter should be an array.');
            }
            array_map(function($value) {
                if (!\in_array($value, self::$availableTypes, true)) {
                    throw new InvalidArgumentException('The "types" parameter is not valid.');
                }
            }, $options['types']);
        }

        if (\is_array($options) && \array_key_exists('versions', $options)) {
            if (!\is_array($options['versions'])) {
                throw new InvalidArgumentException('The "versions" parameter should be an array.');
            }
            array_map(function($value) {
                if (!\in_array($value, self::$availableVersions, true)) {
                    throw new InvalidArgumentException('The "versions" parameter is not valid.');
                }
            }, $options['versions']);
        }

        parent::__construct($options);

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
