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
 * Validates that a card number belongs to a specified scheme.
 *
 * @see http://en.wikipedia.org/wiki/Bank_card_number
 * @author Tim Nagel <t.nagel@infinite.net.au>
 */
class CardSchemeValidator extends ConstraintValidator
{
    protected $schemes = array(
        'AMEX' => array(
            '/^(3[47])([0-9]{13})/'
        ),
        'CHINA_UNIONPAY' => array(
            '/^(62)([0-9]{16,19}/'
        ),
        'DINERS' => array(
            '/^(36)([0-9]{12})/',
            '/^(30[0-5])([0-9]{11})/',
            '/^(5[45])([0-9]{14})/'
        ),
        'DISCOVER' => array(
            '/^(6011)([0-9]{12})/',
            '/^(64[4-9])([0-9]{13})/',
            '/^(65)([0-9]{14})/',
            '/^(622)(12[6-9]|1[3-9][0-9]|[2-8][0-9][0-9]|91[0-9]|92[0-5])([0-9]{10})/'
        ),
        'INSTAPAYMENT' => array(
            '/^(63[7-9])([0-9]{13})/'
        ),
        'JCB' => array(
            '/^(352[8-9]|35[3-8][0-9])([0-9]{12})/'
        ),
        'LASER' => array(
            '/^(6304|670[69]|6771)([0-9]{12, 15})/'
        ),
        'MAESTRO' => array(
            '/^(5018|5020|5038|6304|6759|6761|676[23]|0604)([0-9]{8, 15})/'
        ),
        'MASTERCARD' => array(
            '/^(5[1-5])([0-9]{14})/'
        ),
        'VISA' => array(
            '/^(4)([0-9]{12})/'
        ),
    );

    /**
     * Validates a creditcard belongs to a specified scheme.
     *
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $this->context->addViolation($constraint->message);
        }

        $schemes = array_flip($constraint->schemes);
        $schemeRegexes = array_intersect_key($this->schemes, $schemes);

        foreach ($schemeRegexes as $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $value)) {
                    return;
                }
            }
        }

        $this->context->addViolation($constraint->message);
    }
}
