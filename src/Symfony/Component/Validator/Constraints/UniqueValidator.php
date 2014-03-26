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
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 */
class UniqueValidator extends ConstraintValidator
{

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) && !$value instanceof \Traversable) {
            throw new UnexpectedTypeException($value, 'array or Traversable');
        }

        if ($this->findRepeated($value)) {

            $this->context->addViolation($constraint->message, $params=array());
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
