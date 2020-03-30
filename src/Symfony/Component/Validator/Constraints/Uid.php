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

    const TYPE_UUID = 'UUID';
    const TYPE_ULID = 'ULID';

    const V1 = 1;
    const V3 = 3;
    const V4 = 4;
    const V5 = 5;
    const V6 = 6;

    /**
     * @var string
     */
    public $message = 'This is neither a valid UUID nor ULID.';

    /**
     * @var string
     */
    public $ulidMessage = 'This is not a valid ULID.';

    /**
     * @var string
     */
    public $uuidMessage = 'This is not a valid UUID.';

    /**
     * @var string
     */
    public $versionsMessage = 'This UUID does not match expected versions.';

    /**
     * @var string
     */
    public $type = null;

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
        parent::__construct($options);

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
