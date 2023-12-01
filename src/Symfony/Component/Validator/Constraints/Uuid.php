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
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @author Colin O'Dell <colinodell@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Uuid extends Constraint
{
    public const TOO_SHORT_ERROR = 'aa314679-dac9-4f54-bf97-b2049df8f2a3';
    public const TOO_LONG_ERROR = '494897dd-36f8-4d31-8923-71a8d5f3000d';
    public const INVALID_CHARACTERS_ERROR = '51120b12-a2bc-41bf-aa53-cd73daf330d0';
    public const INVALID_HYPHEN_PLACEMENT_ERROR = '98469c83-0309-4f5d-bf95-a496dcaa869c';
    public const INVALID_VERSION_ERROR = '21ba13b4-b185-4882-ac6f-d147355987eb';
    public const INVALID_TIME_BASED_VERSION_ERROR = '484081ca-6fbd-11ed-ade8-a3bdfd0fcf2f';
    public const INVALID_VARIANT_ERROR = '164ef693-2b9d-46de-ad7f-836201f0c2db';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_HYPHEN_PLACEMENT_ERROR => 'INVALID_HYPHEN_PLACEMENT_ERROR',
        self::INVALID_VERSION_ERROR => 'INVALID_VERSION_ERROR',
        self::INVALID_VARIANT_ERROR => 'INVALID_VARIANT_ERROR',
    ];

    // Possible versions defined by RFC 4122
    public const V1_MAC = 1;
    public const V2_DCE = 2;
    public const V3_MD5 = 3;
    public const V4_RANDOM = 4;
    public const V5_SHA1 = 5;
    public const V6_SORTABLE = 6;
    public const V7_MONOTONIC = 7;
    public const V8_CUSTOM = 8;

    public const ALL_VERSIONS = [
        self::V1_MAC,
        self::V2_DCE,
        self::V3_MD5,
        self::V4_RANDOM,
        self::V5_SHA1,
        self::V6_SORTABLE,
        self::V7_MONOTONIC,
        self::V8_CUSTOM,
    ];

    public const TIME_BASED_VERSIONS = [
        self::V1_MAC,
        self::V6_SORTABLE,
        self::V7_MONOTONIC,
    ];

    /**
     * Message to display when validation fails.
     */
    public string $message = 'This is not a valid UUID.';

    /**
     * Strict mode only allows UUIDs that meet the formal definition and formatting per RFC 4122.
     *
     * Set this to `false` to allow legacy formats with different dash positioning or wrapping characters
     */
    public bool $strict = true;

    /**
     * Array of allowed versions (see version constants above).
     *
     * All UUID versions are allowed by default
     *
     * @var int[]
     */
    public array $versions = self::ALL_VERSIONS;

    /** @var callable|null */
    public $normalizer;

    /**
     * @param int[]|int|null $versions
     */
    public function __construct(
        array $options = null,
        string $message = null,
        array|int $versions = null,
        bool $strict = null,
        callable $normalizer = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->versions = (array) ($versions ?? $this->versions);
        $this->strict = $strict ?? $this->strict;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
