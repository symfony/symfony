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
 * Validates that a backed enum can be hydrated from a value.
 *
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class BackedEnumValue extends Constraint
{
    public const NO_SUCH_VALUE_ERROR = '53dcc1b1-a8dd-4813-baa5-b8486ff56447';
    public const INVALID_TYPE_ERROR = 'aa0374f4-b3ab-4362-b48d-b5ecf0f1a02d';

    protected const ERROR_NAMES = [
        self::NO_SUCH_VALUE_ERROR => 'NO_SUCH_VALUE_ERROR',
        self::INVALID_TYPE_ERROR => 'INVALID_TYPE_ERROR',
    ];

    /**
     * @param class-string<\BackedEnum> $type   the type of the enum
     * @param \BackedEnum[]             $except the cases that should be considered invalid
     */
    #[HasNamedArguments]
    public function __construct(
        public string $type,
        public array $except = [],
        public string $message = 'The value you selected is not a valid choice.',
        public string $typeMessage = 'This value should be of type {{ type }}.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        if (!is_a($type, \BackedEnum::class, true)) {
            throw new ConstraintDefinitionException(sprintf('The "type" must be a \BackedEnum, got "%s".', get_debug_type($type)));
        }

        foreach ($except as $exceptValue) {
            if (!is_a($exceptValue, $type)) {
                throw new ConstraintDefinitionException(sprintf('The "except" values must be cases of enum "%s", got "%s".', $type, get_debug_type($exceptValue)));
            }
        }
    }
}
