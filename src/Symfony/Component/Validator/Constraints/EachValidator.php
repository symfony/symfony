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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraints\AbstractCompositeValidator;

/**
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class EachValidator extends AbstractCompositeValidator
{

    /**
     * {@inheritDoc}
     */
    public function doValidate($value, Constraint $constraint)
    {
        $group = $this->context->getGroup();

        foreach ($value as $key => $element) {
            foreach ($constraint->constraints as $constr) {
                $this->context->validateValue($element, $constr, '[' . $key . ']', $group);
            }
        }
    }
}
