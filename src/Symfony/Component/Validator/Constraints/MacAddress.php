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

    public string $message = 'This is not a valid MAC address.';

    /** @var callable|null */
    public $normalizer;

    public function __construct(
        array $options = null,
        string $message = null,
        callable $normalizer = null,
        array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
