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
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Validates that a value is not blank.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class NotBlank extends Constraint
{
    public const IS_BLANK_ERROR = 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

    protected const ERROR_NAMES = [
        self::IS_BLANK_ERROR => 'IS_BLANK_ERROR',
    ];

    public string $message = 'This value should not be blank.';
    public bool $allowNull = false;
    /** @var callable|null */
    public $normalizer;

    /**
     * @param array<string,mixed>|null $options
     * @param bool|null                $allowNull Whether to allow null values (defaults to false)
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?string $message = null, ?bool $allowNull = null, ?callable $normalizer = null, ?array $groups = null, mixed $payload = null)
    {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->allowNull = $allowNull ?? $this->allowNull;
        $this->normalizer = $normalizer ?? $this->normalizer;

        if (null !== $this->normalizer && !\is_callable($this->normalizer)) {
            throw new InvalidArgumentException(\sprintf('The "normalizer" option must be a valid callable ("%s" given).', get_debug_type($this->normalizer)));
        }
    }
}
