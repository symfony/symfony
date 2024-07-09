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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates that a value is a valid MAC address.
 *
 * @author Ninos Ego <me@ninosego.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class MacAddress extends Constraint
{
    public const ALL = 'all';
    public const ALL_NO_BROADCAST = 'all_no_broadcast';
    public const LOCAL_ALL = 'local_all';
    public const LOCAL_NO_BROADCAST = 'local_no_broadcast';
    public const LOCAL_UNICAST = 'local_unicast';
    public const LOCAL_MULTICAST = 'local_multicast';
    public const LOCAL_MULTICAST_NO_BROADCAST = 'local_multicast_no_broadcast';
    public const UNIVERSAL_ALL = 'universal_all';
    public const UNIVERSAL_UNICAST = 'universal_unicast';
    public const UNIVERSAL_MULTICAST = 'universal_multicast';
    public const UNICAST_ALL = 'unicast_all';
    public const MULTICAST_ALL = 'multicast_all';
    public const MULTICAST_NO_BROADCAST = 'multicast_no_broadcast';
    public const BROADCAST = 'broadcast';

    public const INVALID_MAC_ERROR = 'a183fbff-6968-43b4-82a2-cc5cf7150036';

    private const TYPES = [
        self::ALL,
        self::ALL_NO_BROADCAST,
        self::LOCAL_ALL,
        self::LOCAL_NO_BROADCAST,
        self::LOCAL_UNICAST,
        self::LOCAL_MULTICAST,
        self::LOCAL_MULTICAST_NO_BROADCAST,
        self::UNIVERSAL_ALL,
        self::UNIVERSAL_UNICAST,
        self::UNIVERSAL_MULTICAST,
        self::UNICAST_ALL,
        self::MULTICAST_ALL,
        self::MULTICAST_NO_BROADCAST,
        self::BROADCAST,
    ];

    protected const ERROR_NAMES = [
        self::INVALID_MAC_ERROR => 'INVALID_MAC_ERROR',
    ];

    public ?\Closure $normalizer;

    /**
     * @param self::ALL*|self::LOCAL_*|self::UNIVERSAL_*|self::UNICAST_*|self::MULTICAST_*|self::BROADCAST $type A mac address type to validate (defaults to {@see self::ALL})
     */
    #[HasNamedArguments]
    public function __construct(
        public string $message = 'This value is not a valid MAC address.',
        public string $type = self::ALL,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        if (!\in_array($this->type, self::TYPES, true)) {
            throw new ConstraintDefinitionException(\sprintf('The option "type" must be one of "%s".', implode('", "', self::TYPES)));
        }

        $this->normalizer = null !== $normalizer ? $normalizer(...) : null;
    }
}
