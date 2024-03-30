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

use Symfony\Component\Intl\Currencies;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Validates that a value is a valid 3-letter ISO 4217 currency name.
 *
 * @see https://en.wikipedia.org/wiki/ISO_4217
 *
 * @author Miha Vrhovnik <miha.vrhovnik@pagein.si>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Currency extends Constraint
{
    public const NO_SUCH_CURRENCY_ERROR = '69945ac1-2db4-405f-bec7-d2772f73df52';

    protected const ERROR_NAMES = [
        self::NO_SUCH_CURRENCY_ERROR => 'NO_SUCH_CURRENCY_ERROR',
    ];

    public string $message = 'This value is not a valid currency.';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (!class_exists(Currencies::class)) {
            throw new LogicException('The Intl component is required to use the Currency constraint. Try running "composer require symfony/intl".');
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
