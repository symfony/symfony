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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Validates a credit card number for a given credit card company.
 *
 * @author Tim Nagel <t.nagel@infinite.net.au>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CardScheme extends Constraint
{
    public const AMEX = 'AMEX';
    public const CHINA_UNIONPAY = 'CHINA_UNIONPAY';
    public const DINERS = 'DINERS';
    public const DISCOVER = 'DISCOVER';
    public const INSTAPAYMENT = 'INSTAPAYMENT';
    public const JCB = 'JCB';
    public const LASER = 'LASER';
    public const MAESTRO = 'MAESTRO';
    public const MASTERCARD = 'MASTERCARD';
    public const MIR = 'MIR';
    public const UATP = 'UATP';
    public const VISA = 'VISA';

    public const NOT_NUMERIC_ERROR = 'a2ad9231-e827-485f-8a1e-ef4d9a6d5c2e';
    public const INVALID_FORMAT_ERROR = 'a8faedbf-1c2f-4695-8d22-55783be8efed';

    protected const ERROR_NAMES = [
        self::NOT_NUMERIC_ERROR => 'NOT_NUMERIC_ERROR',
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    public string $message = 'Unsupported card type or invalid card number.';
    public array|string|null $schemes = null;

    /**
     * @param non-empty-string|non-empty-string[]|null $schemes Name(s) of the number scheme(s) used to validate the credit card number
     * @param string[]|null                            $groups
     */
    public function __construct(array|string|null $schemes, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (null === $schemes) {
            throw new MissingOptionsException(\sprintf('The options "schemes" must be set for constraint "%s".', self::class), ['schemes']);
        }

        parent::__construct(null, $groups, $payload);

        $this->schemes = $schemes;
        $this->message = $message ?? $this->message;
    }
}
