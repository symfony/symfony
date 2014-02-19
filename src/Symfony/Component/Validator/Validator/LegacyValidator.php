<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LegacyValidator extends Validator implements LegacyValidatorInterface
{
    public function validate($value, $groups = null, $traverse = false, $deep = false)
    {
        // Use new signature if constraints are given in the second argument
        if (func_num_args() <= 3 && ($groups instanceof Constraint || (is_array($groups) && current($groups) instanceof Constraint))) {
            return parent::validate($value, $groups, $traverse);
        }

        if (is_array($value)) {
            $constraint = new Traverse(array(
                'traverse' => true,
                'deep' => $deep,
            ));

            return parent::validate($value, $constraint, $groups);
        }

        if ($traverse && $value instanceof \Traversable) {
            $constraints = array(
                new Valid(),
                new Traverse(array('traverse' => true, 'deep' => $deep)),
            );

            return parent::validate($value, $constraints, $groups);
        }

        return $this->validateObject($value, $groups);
    }

    public function validateValue($value, $constraints, $groups = null)
    {
        return parent::validate($value, $constraints, $groups);
    }

    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }
}
