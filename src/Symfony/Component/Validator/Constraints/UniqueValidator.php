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
use Symfony\Component\Validator\Constraints\AbstractCompositeValidator;

/**
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class UniqueValidator extends AbstractCompositeValidator
{

    /**
     * {@inheritDoc}
     */
    public function doValidate($value, Constraint $constraint)
    {
        if ($this->findRepeated($value)){

            $this->context->addViolation($constraint->uniqueMessage, $params=array());
        }
    }

    /**
     * Given a set of iterable elements, just checks if all elements are once
     *
     * @param Mixed $elements Elements to check
     *
     * @return boolean Elements in collection are once
     */
    private function findRepeated($elements)
    {
        $arrayUnique = array();

        foreach ($elements as $element) {

            $arrayUnique[serialize($element)] = $element;
        }

        return (count($arrayUnique) < count($elements));
    }
}
