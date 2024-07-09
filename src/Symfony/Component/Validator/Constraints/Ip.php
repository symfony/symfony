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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Validates that a value is a valid IP address.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 * @author Ninos Ego <me@ninosego.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Ip extends Constraint
{
    public const V4 = '4';
    public const V6 = '6';
    public const ALL = 'all';

    // adds inverse FILTER_FLAG_NO_RES_RANGE and FILTER_FLAG_NO_PRIV_RANGE flags (skip both)
    public const V4_NO_PUBLIC = '4_no_public';
    public const V6_NO_PUBLIC = '6_no_public';
    public const ALL_NO_PUBLIC = 'all_no_public';

    // adds FILTER_FLAG_NO_PRIV_RANGE flag (skip private ranges)
    public const V4_NO_PRIVATE = '4_no_priv';
    public const V4_NO_PRIV = self::V4_NO_PRIVATE; // BC: Alias
    public const V6_NO_PRIVATE = '6_no_priv';
    public const V6_NO_PRIV = self::V6_NO_PRIVATE; // BC: Alias
    public const ALL_NO_PRIVATE = 'all_no_priv';
    public const ALL_NO_PRIV = self::ALL_NO_PRIVATE; // BC: Alias

    // adds FILTER_FLAG_NO_RES_RANGE flag (skip reserved ranges)
    public const V4_NO_RESERVED = '4_no_res';
    public const V4_NO_RES = self::V4_NO_RESERVED; // BC: Alias
    public const V6_NO_RESERVED = '6_no_res';
    public const V6_NO_RES = self::V6_NO_RESERVED; // BC: Alias
    public const ALL_NO_RESERVED = 'all_no_res';
    public const ALL_NO_RES = self::ALL_NO_RESERVED; // BC: Alias

    // adds FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE flags (skip both)
    public const V4_ONLY_PUBLIC = '4_public';
    public const V6_ONLY_PUBLIC = '6_public';
    public const ALL_ONLY_PUBLIC = 'all_public';

    // adds inverse FILTER_FLAG_NO_PRIV_RANGE
    public const V4_ONLY_PRIVATE = '4_private';
    public const V6_ONLY_PRIVATE = '6_private';
    public const ALL_ONLY_PRIVATE = 'all_private';

    // adds inverse FILTER_FLAG_NO_RES_RANGE
    public const V4_ONLY_RESERVED = '4_reserved';
    public const V6_ONLY_RESERVED = '6_reserved';
    public const ALL_ONLY_RESERVED = 'all_reserved';

    public const INVALID_IP_ERROR = 'b1b427ae-9f6f-41b0-aa9b-84511fbb3c5b';

    protected const VERSIONS = [
        self::V4,
        self::V6,
        self::ALL,

        self::V4_NO_PUBLIC,
        self::V6_NO_PUBLIC,
        self::ALL_NO_PUBLIC,

        self::V4_NO_PRIVATE,
        self::V6_NO_PRIVATE,
        self::ALL_NO_PRIVATE,

        self::V4_NO_RESERVED,
        self::V6_NO_RESERVED,
        self::ALL_NO_RESERVED,

        self::V4_ONLY_PUBLIC,
        self::V6_ONLY_PUBLIC,
        self::ALL_ONLY_PUBLIC,

        self::V4_ONLY_PRIVATE,
        self::V6_ONLY_PRIVATE,
        self::ALL_ONLY_PRIVATE,

        self::V4_ONLY_RESERVED,
        self::V6_ONLY_RESERVED,
        self::ALL_ONLY_RESERVED,
    ];

    protected const ERROR_NAMES = [
        self::INVALID_IP_ERROR => 'INVALID_IP_ERROR',
    ];

    public string $version = self::V4;
    public string $message = 'This is not a valid IP address.';
    /** @var callable|null */
    public $normalizer;

    /**
     * @param array<string,mixed>|null            $options
     * @param self::V4*|self::V6*|self::ALL*|null $version The IP version to validate (defaults to {@see self::V4})
     * @param string[]|null                       $groups
     */
    public function __construct(
        ?array $options = null,
        ?string $version = null,
        ?string $message = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->version = $version ?? $this->version;
        $this->message = $message ?? $this->message;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (!\in_array($this->version, static::VERSIONS, true)) {
            throw new ConstraintDefinitionException(\sprintf('The option "version" must be one of "%s".', implode('", "', static::VERSIONS)));
        }

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(\sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
