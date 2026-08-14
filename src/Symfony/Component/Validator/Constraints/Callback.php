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
 * Defines custom validation rules through arbitrary callback methods.
 *
 * The callback can build violations using the execution context passed to it.
 * In addition, when the "message" option is set, the callback must return a
 * boolean and a violation is built automatically with that message when it
 * returns false. When "message" is not set, the return value is ignored:
 *
 *     #[Assert\Callback(
 *         static function (\DateTimeImmutable $value) {
 *             return $value > new \DateTimeImmutable('today');
 *         },
 *         message: 'The delivery date must be in the future.',
 *     )]
 *     public \DateTimeImmutable $deliveryDate;
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Callback extends Constraint
{
    public const NOT_SATISFIED_ERROR = '9f986b1b-4c81-4b33-ad55-3e2cae0ea562';

    protected const ERROR_NAMES = [
        self::NOT_SATISFIED_ERROR => 'NOT_SATISFIED_ERROR',
    ];

    /**
     * @var string|callable
     */
    public $callback;

    public ?string $message = null;

    /**
     * @param string|callable|null $callback The callback definition
     * @param string[]|null        $groups
     * @param string|null          $message  The violation message raised when the callback returns false; the callback must then return a boolean. When not set, the return value of the callback is ignored
     */
    public function __construct(string|callable|null $callback = null, ?array $groups = null, mixed $payload = null, ?string $message = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->callback = $callback;
        $this->message = $message;
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }
}
