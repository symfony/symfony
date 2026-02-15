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
 * Validates that a value is one of a given set of valid choices.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Choice extends Constraint
{
    public const NO_SUCH_CHOICE_ERROR = '8e179f1b-97aa-4560-a02f-2a8b42e49df7';
    public const TOO_FEW_ERROR = '11edd7eb-5872-4b6e-9f12-89923999fd0e';
    public const TOO_MANY_ERROR = '9bd98e49-211c-433f-8630-fd1c2d0f08c3';

    protected const ERROR_NAMES = [
        self::NO_SUCH_CHOICE_ERROR => 'NO_SUCH_CHOICE_ERROR',
        self::TOO_FEW_ERROR => 'TOO_FEW_ERROR',
        self::TOO_MANY_ERROR => 'TOO_MANY_ERROR',
    ];

    public ?array $choices = null;
    /** @var callable|string|null */
    public $callback;
    public bool $multiple = false;
    public bool $strict = true;
    public ?int $min = null;
    public ?int $max = null;
    public string $message = 'The value you selected is not a valid choice.';
    public string $multipleMessage = 'One or more of the given values is invalid.';
    public string $minMessage = 'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.';
    public string $maxMessage = 'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.';
    public bool $match = true;

    /**
     * @param array|null           $choices  An array of choices (required unless a callback is specified)
     * @param callable|string|null $callback Callback method to use instead of the choice option to get the choices
     * @param bool|null            $multiple Whether to expect the value to be an array of valid choices (defaults to false)
     * @param bool|null            $strict   This option defaults to true and should not be used
     * @param int<0, max>|null     $min      Minimum of valid choices if multiple values are expected
     * @param positive-int|null    $max      Maximum of valid choices if multiple values are expected
     * @param string[]|null        $groups
     * @param bool|null            $match    Whether to validate the values are part of the choices or not (defaults to true)
     */
    public function __construct(
        string|array|null $options = null,
        ?array $choices = null,
        callable|string|null $callback = null,
        ?bool $multiple = null,
        ?bool $strict = null,
        ?int $min = null,
        ?int $max = null,
        ?string $message = null,
        ?string $multipleMessage = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?array $groups = null,
        mixed $payload = null,
        ?bool $match = null,
    ) {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(null, $groups, $payload);

        $this->choices = $choices;
        $this->callback = $callback;
        $this->multiple = $multiple ?? $this->multiple;
        $this->strict = $strict ?? $this->strict;
        $this->min = $min;
        $this->max = $max;
        $this->message = $message ?? $this->message;
        $this->multipleMessage = $multipleMessage ?? $this->multipleMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->match = $match ?? $this->match;
    }
}
