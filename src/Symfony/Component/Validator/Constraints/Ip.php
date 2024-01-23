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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Ip extends Constraint
{
    public const V4 = '4';
    public const V6 = '6';
    public const ALL = 'all';

    // adds FILTER_FLAG_NO_PRIV_RANGE flag (skip private ranges)
    public const V4_NO_PRIV = '4_no_priv';
    public const V6_NO_PRIV = '6_no_priv';
    public const ALL_NO_PRIV = 'all_no_priv';

    // adds FILTER_FLAG_NO_RES_RANGE flag (skip reserved ranges)
    public const V4_NO_RES = '4_no_res';
    public const V6_NO_RES = '6_no_res';
    public const ALL_NO_RES = 'all_no_res';

    // adds FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE flags (skip both)
    public const V4_ONLY_PUBLIC = '4_public';
    public const V6_ONLY_PUBLIC = '6_public';
    public const ALL_ONLY_PUBLIC = 'all_public';

    public const INVALID_IP_ERROR = 'b1b427ae-9f6f-41b0-aa9b-84511fbb3c5b';

    protected const VERSIONS = [
        self::V4,
        self::V6,
        self::ALL,

        self::V4_NO_PRIV,
        self::V6_NO_PRIV,
        self::ALL_NO_PRIV,

        self::V4_NO_RES,
        self::V6_NO_RES,
        self::ALL_NO_RES,

        self::V4_ONLY_PUBLIC,
        self::V6_ONLY_PUBLIC,
        self::ALL_ONLY_PUBLIC,
    ];

    protected const ERROR_NAMES = [
        self::INVALID_IP_ERROR => 'INVALID_IP_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const VERSIONS instead
     */
    protected static $versions = self::VERSIONS;

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $version = self::V4;

    public $message = 'This is not a valid IP address.';

    /** @var callable|null */
    public $normalizer;

    public function __construct(
        ?array $options = null,
        ?string $version = null,
        ?string $message = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->version = $version ?? $this->version;
        $this->message = $message ?? $this->message;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (!\in_array($this->version, self::$versions)) {
            throw new ConstraintDefinitionException(sprintf('The option "version" must be one of "%s".', implode('", "', self::$versions)));
        }

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
