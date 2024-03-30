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
 * Validates that a value is a valid CIDR notation.
 *
 * @see https://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing
 *
 * @author Sorin Pop <popsorin15@gmail.com>
 * @author Calin Bolea <calin.bolea@gmail.com>
 * @author Ninos Ego <me@ninosego.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Cidr extends Constraint
{
    public const INVALID_CIDR_ERROR = '5649e53a-5afb-47c5-a360-ffbab3be8567';
    public const OUT_OF_RANGE_ERROR = 'b9f14a51-acbd-401a-a078-8c6b204ab32f';

    protected const ERROR_NAMES = [
        self::INVALID_CIDR_ERROR => 'INVALID_CIDR_ERROR',
        self::OUT_OF_RANGE_ERROR => 'OUT_OF_RANGE_VIOLATION',
    ];

    private const NET_MAXES = [
        Ip::V4 => 32,
        Ip::V6 => 128,
        Ip::ALL => 128,

        Ip::V4_NO_PUBLIC => 32,
        Ip::V6_NO_PUBLIC => 128,
        Ip::ALL_NO_PUBLIC => 128,

        Ip::V4_NO_PRIVATE => 32,
        Ip::V6_NO_PRIVATE => 128,
        Ip::ALL_NO_PRIVATE => 128,

        Ip::V4_NO_RESERVED => 32,
        Ip::V6_NO_RESERVED => 128,
        Ip::ALL_NO_RESERVED => 128,

        Ip::V4_ONLY_PUBLIC => 32,
        Ip::V6_ONLY_PUBLIC => 128,
        Ip::ALL_ONLY_PUBLIC => 128,

        Ip::V4_ONLY_PRIVATE => 32,
        Ip::V6_ONLY_PRIVATE => 128,
        Ip::ALL_ONLY_PRIVATE => 128,

        Ip::V4_ONLY_RESERVED => 32,
        Ip::V6_ONLY_RESERVED => 128,
        Ip::ALL_ONLY_RESERVED => 128,
    ];

    public string $version = Ip::ALL;
    public string $message = 'This value is not a valid CIDR notation.';
    public string $netmaskRangeViolationMessage = 'The value of the netmask should be between {{ min }} and {{ max }}.';
    public int $netmaskMin = 0;
    public int $netmaskMax;

    /** @var callable|null */
    public $normalizer;

    public function __construct(
        ?array $options = null,
        ?string $version = null,
        ?int $netmaskMin = null,
        ?int $netmaskMax = null,
        ?string $message = null,
        ?array $groups = null,
        $payload = null,
        ?callable $normalizer = null,
    ) {
        $this->version = $version ?? $options['version'] ?? $this->version;

        if (!\array_key_exists($this->version, self::NET_MAXES)) {
            throw new ConstraintDefinitionException(sprintf('The option "version" must be one of "%s".', implode('", "', array_keys(self::NET_MAXES))));
        }

        $this->netmaskMin = $netmaskMin ?? $options['netmaskMin'] ?? $this->netmaskMin;
        $this->netmaskMax = $netmaskMax ?? $options['netmaskMax'] ?? self::NET_MAXES[$this->version];
        $this->message = $message ?? $this->message;
        $this->normalizer = $normalizer ?? $this->normalizer;

        unset($options['netmaskMin'], $options['netmaskMax'], $options['version']);

        if ($this->netmaskMin < 0 || $this->netmaskMax > self::NET_MAXES[$this->version] || $this->netmaskMin > $this->netmaskMax) {
            throw new ConstraintDefinitionException(sprintf('The netmask range must be between 0 and %d.', self::NET_MAXES[$this->version]));
        }

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }

        parent::__construct($options, $groups, $payload);
    }
}
