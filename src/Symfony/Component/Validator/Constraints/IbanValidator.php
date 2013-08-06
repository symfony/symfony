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

/**
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Michael Schummel
 * @link http://www.michael-schummel.de/2007/10/05/iban-prufung-mit-php/
 */
class IbanValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        $teststring = preg_replace('/\s+/', '', $value);

        if (strlen($teststring) < 4) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));

            return;
        }

        $teststring = substr($teststring, 4)
            .strval(ord($teststring{0}) - 55)
            .strval(ord($teststring{1}) - 55)
            .substr($teststring, 2, 2);

        $teststring = preg_replace_callback('/[A-Za-z]/', function ($letter) {
            return intval(ord(strtolower($letter[0])) - 87);
        }, $teststring);

        $rest = 0;
        $strlen = strlen($teststring);
        for ($pos = 0; $pos < $strlen; $pos += 7) {
            $part = strval($rest).substr($teststring, $pos, 7);
            $rest = intval($part) % 97;
        }

        if ($rest != 1) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));

            return;
        }
    }
}
