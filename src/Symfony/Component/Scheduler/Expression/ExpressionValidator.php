<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Expression;

use Symfony\Component\Scheduler\Exception\InvalidExpressionException;
use Symfony\Component\Scheduler\Expression\Validator\DaysOfMonthExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\DaysOfWeekExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\ExpressionValidatorInterface;
use Symfony\Component\Scheduler\Expression\Validator\HoursExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MacroExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MinutesExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MonthsExpressionValidator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionValidator
{
    private $validators;

    /**
     * @param iterable|ExpressionValidatorInterface[] $validators
     */
    public function __construct(iterable $validators)
    {
        $this->validators = $validators;
    }

    public function validate(string $expression): bool
    {
        $fields = preg_split('/\s/', $expression, -1, PREG_SPLIT_NO_EMPTY);

        if (1 === \count($fields) && 0 === strpos($expression, '@')) {
            return $this->getValidator((new MacroExpressionValidator())->getPosition())->isValid($expression);
        }

        if (5 !== \count($fields)) {
            throw new InvalidExpressionException(sprintf('The expression "%s" is invalid, please check the syntax.', $expression));
        }

        try {
            foreach ($fields as $position => $value) {
                $validator = $this->getValidator($position);

                if (!$validator->isValid($value)) {
                    throw new InvalidExpressionException(sprintf('The expression part "%s" at position "%d" is invalid', $value, $position));
                }
            }
        } catch (InvalidExpressionException $exception) {
            return false;
        }

        return true;
    }

    public static function validateString(string $expression): bool
    {
        $self = new self([
            new DaysOfMonthExpressionValidator(),
            new DaysOfWeekExpressionValidator(),
            new HoursExpressionValidator(),
            new MacroExpressionValidator(),
            new MinutesExpressionValidator(),
            new MonthsExpressionValidator(),
        ]);

        return $self->validate($expression);
    }

    private function getValidator(int $position): ExpressionValidatorInterface
    {
        foreach ($this->validators as $validator) {
            if ($position !== $validator->getPosition()) {
                continue;
            }

            return $validator;
        }

        throw new InvalidExpressionException(sprintf('The given position "%d" is invalid!', $position));
    }
}
