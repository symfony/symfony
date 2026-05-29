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

use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Validates that a value is an E.164-formatted phone number.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PhoneNumber extends Constraint
{
    /**
     * Checks that the value matches the E.164 syntax.
     */
    public const MODE_E164 = 'e164';

    /**
     * Additionally checks, via the "giggsey/libphonenumber-for-php-lite" library, that the value
     * is valid for a supported numbering plan.
     */
    public const MODE_STRICT = 'strict';

    public const INVALID_FORMAT_ERROR = 'd0e5c149-1159-407d-b600-3fdc82b95824';
    public const INVALID_PHONE_NUMBER_ERROR = '2cbb7633-5a07-4228-9457-84f8e6d71f37';

    public const VALIDATION_MODES = [
        self::MODE_E164,
        self::MODE_STRICT,
    ];

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_PHONE_NUMBER_ERROR => 'INVALID_PHONE_NUMBER_ERROR',
    ];

    /** @var callable|null */
    public $normalizer;

    /**
     * @param self::MODE_*  $mode       One of {@see self::MODE_E164} or {@see self::MODE_STRICT}
     * @param callable|null $normalizer A callable to normalize the value before it is validated
     * @param string[]|null $groups
     */
    public function __construct(
        public string $message = 'This value is not a valid phone number.',
        public string $mode = self::MODE_E164,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!\in_array($mode, self::VALIDATION_MODES, true)) {
            throw new InvalidArgumentException('The "mode" parameter value is not valid.');
        }

        if (self::MODE_STRICT === $mode && !class_exists(PhoneNumberUtil::class)) {
            throw new LogicException(\sprintf('The "giggsey/libphonenumber-for-php-lite" library is required to use the "%s" constraint in strict mode. Try running "composer require giggsey/libphonenumber-for-php-lite".', __CLASS__));
        }

        parent::__construct(null, $groups, $payload);

        $this->normalizer = $normalizer;
    }
}
