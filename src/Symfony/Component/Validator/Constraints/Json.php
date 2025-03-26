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

/**
 * Validates that a value has valid JSON syntax.
 *
 * @author Imad ZAIRIG <imadzairig@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Json extends Constraint
{
    public const INVALID_JSON_ERROR = '0789c8ad-2d2b-49a4-8356-e2ce63998504';

    protected const ERROR_NAMES = [
        self::INVALID_JSON_ERROR => 'INVALID_JSON_ERROR',
    ];

    public string $message = 'This value should be valid JSON.';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
