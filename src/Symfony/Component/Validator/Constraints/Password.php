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
 * @author Jérémy Reynaud <jeremy@reynaud.io>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Password extends Constraint
{
    public const MIN_ERROR = '520975d5-94e6-416b-8571-4ae99a3ceb12';
    public const MIXED_CASE_ERROR = '9d15ccfa-4981-46f0-bf4a-e38bc916f7bd';
    public const LETTERS_ERROR = 'b58d02f0-233e-4a3c-9939-5084a678aa83';
    public const NUMBERS_ERROR = 'b8721890-6e05-49db-8625-5039f3d99928';
    public const SYMBOLS_ERROR = '17923814-85cc-43df-ab66-e37d2e80397b';

    public function __construct(
        array $options = null,

        /** The minimum size of the password. */
        public int $min = 12,
        public string $minMessage = 'The password value is too short. It should have {{ min }} characters or more.',

        /** If the password requires at least one uppercase and one lowercase letter. */
        public bool $mixedCase = false,
        public string $mixedCaseMessage = 'The password must contain at least one uppercase and one lowercase letter.',

        /** If the password requires at least one letter. */
        public bool $letters = false,
        public string $lettersMessage = 'The password must contain at least one letter.',

        /** If the password requires at least one number. */
        public bool $numbers = false,
        public string $numbersMessage = 'The password must contain at least one number.',

        /** If the password requires at least one symbol. */
        public bool $symbols = false,
        public string $symbolsMessage = 'The password must contain at least one symbol.',

        array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return PasswordValidator::class;
    }
}
