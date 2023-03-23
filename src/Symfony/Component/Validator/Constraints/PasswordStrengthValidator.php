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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use ZxcvbnPhp\Matchers\DictionaryMatch;
use ZxcvbnPhp\Matchers\MatchInterface;
use ZxcvbnPhp\Zxcvbn;

final class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate(#[\SensitiveParameter] mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $zxcvbn = new Zxcvbn();
        $strength = $zxcvbn->passwordStrength($value, $constraint->restrictedData);

        if ($strength['score'] < $constraint->minScore) {
            $this->context->buildViolation($constraint->lowStrengthMessage)
                ->setCode(PasswordStrength::PASSWORD_STRENGTH_ERROR)
                ->addViolation();
        }
        $wordList = $this->findRestrictedUserInputs($strength['sequence'] ?? []);
        if (0 !== \count($wordList)) {
            $this->context->buildViolation($constraint->restrictedDataMessage, [
                '{{ wordList }}' => implode(', ', $wordList),
            ])
                ->setCode(PasswordStrength::RESTRICTED_USER_INPUT_ERROR)
                ->addViolation();
        }
    }

    /**
     * @param array<MatchInterface> $sequence
     *
     * @return array<string>
     */
    private function findRestrictedUserInputs(array $sequence): array
    {
        $found = [];

        foreach ($sequence as $item) {
            if (!$item instanceof DictionaryMatch) {
                continue;
            }
            if ('user_inputs' === $item->dictionaryName) {
                $found[] = $item->token;
            }
        }

        return $found;
    }
}
