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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

abstract class AbstractStringContainsValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AbstractStringContains) {
            throw new UnexpectedTypeException($constraint, AbstractStringContains::class);
        }

        $this->checkTextSource($constraint);

        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $texts = $this->resolveTextOption($constraint);
        $this->doValidate($value, $texts, $constraint);
    }

    abstract protected function doValidate(string $value, iterable $texts, AbstractStringContains $constraint): void;

    private function checkTextSource(AbstractStringContains $constraint): void
    {
        if (!isset($constraint->text) && !isset($constraint->callback)) {
            throw new ConstraintDefinitionException(sprintf(
                'The "%s" constraint requires either the "text" or "callback" option to be set.',
                \get_class($this)
            ));
        }

        if (!(isset($constraint->text) xor isset($constraint->callback))) {
            throw new ConstraintDefinitionException(sprintf(
                'The "%s" constraint requires only one of the "text" or "callback" options to be set.',
                \get_class($this)
            ));
        }

        if (isset($constraint->text) && !\is_string($constraint->text) && !is_iterable($constraint->text)) {
            throw new ConstraintDefinitionException(sprintf(
                'The "%s" "text" option value must be string or iterable.',
                \get_class($this)
            ));
        }
    }

    private function resolveTextOption(AbstractStringContains $constraint): iterable
    {
        if (isset($constraint->callback)) {
            $text = $this->loadTextFromCallback($constraint);
        } else {
            $text = $constraint->text;
        }

        if (!\is_string($text) && !is_iterable($text)) {
            throw new ConstraintDefinitionException(sprintf(
                'The %s constraint expects string or iterable as expected.',
                \get_class($constraint)
            ));
        }

        if (!is_iterable($text)) {
            $text = [$text];
        }

        return $text;
    }

    private function loadTextFromCallback(AbstractStringContains $constraint)
    {
        if (!\is_callable($callback = [$this->context->getObject(), $constraint->callback])
            && !\is_callable($callback = [$this->context->getClassName(), $constraint->callback])
            && !\is_callable($callback = $constraint->callback)
        ) {
            throw new ConstraintDefinitionException('The AbstractStringContains constraint expects a valid callback.');
        }

        return $callback($this->context, $constraint->payload);
    }
}
