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
 * Validates that a value is a valid MAC address.
 *
 * @author Ninos Ego <me@ninosego.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class MacAddress extends Constraint
{
    public const INVALID_MAC_ERROR = 'a183fbff-6968-43b4-82a2-cc5cf7150036';

    protected const ERROR_NAMES = [
        self::INVALID_MAC_ERROR => 'INVALID_MAC_ERROR',
    ];

    public ?\Closure $normalizer;

    public function __construct(
        public string $message = 'This value is not a valid MAC address.',
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->normalizer = null !== $normalizer ? $normalizer(...) : null;
    }
}
