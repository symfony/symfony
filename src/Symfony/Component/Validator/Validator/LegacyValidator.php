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
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * A validator that supports both the API of Symfony < 2.5 and Symfony 2.5+.
 *
 * This class is incompatible with PHP versions < 5.3.9, because it implements
 * two different interfaces specifying the same method validate():
 *
 *   - {@link \Symfony\Component\Validator\ValidatorInterface}
 *   - {@link \Symfony\Component\Validator\Validator\ValidatorInterface}
 *
 * In PHP versions prior to 5.3.9, either use {@link RecursiveValidator} or the
 * deprecated class {@link \Symfony\Component\Validator\Validator} instead.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see \Symfony\Component\Validator\ValidatorInterface
 * @see \Symfony\Component\Validator\Validator\ValidatorInterface
 *
 * @deprecated Implemented for backwards compatibility with Symfony < 2.5.
 *             To be removed in Symfony 3.0.
 */
class LegacyValidator extends RecursiveValidator implements LegacyValidatorInterface
{
    public function validate($value, $groups = null, $traverse = false, $deep = false)
    {
        $numArgs = func_num_args();

        // Use new signature if constraints are given in the second argument
        if (self::testConstraints($groups) && ($numArgs < 2 || 3 === $numArgs && self::testGroups($traverse))) {
            // Rename to avoid total confusion ;)
            $constraints = $groups;
            $groups = $traverse;

            return parent::validate($value, $constraints, $groups);
        }

        $constraint = new Valid(array('traverse' => $traverse, 'deep' => $deep));

        return parent::validate($value, $constraint, $groups);
    }

    public function validateValue($value, $constraints, $groups = null)
    {
        return parent::validate($value, $constraints, $groups);
    }

    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    private static function testConstraints($constraints)
    {
        return null === $constraints || $constraints instanceof Constraint || (is_array($constraints) && current($constraints) instanceof Constraint);
    }

    private static function testGroups($groups)
    {
        return null === $groups || is_string($groups) || $groups instanceof GroupSequence || (is_array($groups) && (is_string(current($groups)) || current($groups) instanceof GroupSequence));
    }
}
