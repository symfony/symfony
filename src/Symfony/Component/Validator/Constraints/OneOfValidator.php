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

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class OneOfValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof OneOf) {
            throw new UnexpectedTypeException($constraint, OneOf::class);
        }

        $validator = $this->context->getValidator();

        $messages = [$constraint->message];

        foreach ($constraint->constraints as $key => $item) {
            $violations = $validator->validate($value, $item);

            if (0 === \count($violations)) {
                return;
            }

            if ($constraint->includeInternalMessages) {
                $message = ' ['.($key + 1).'] ';

                if ($item instanceof All || $item instanceof Collection) {
                    $message .= $constraint->messageCollection;
                } else {
                    $message .= $violations->get(0)->getMessage();
                }

                $messages[] = $message;
            }
        }

        $this->context->buildViolation(implode('', $messages))
            ->setCode(OneOf::ONE_OF_ERROR)
            ->addViolation()
        ;
    }
}
