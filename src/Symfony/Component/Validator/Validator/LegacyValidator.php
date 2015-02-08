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

use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * A validator that supports both the API of Symfony < 2.5 and Symfony 2.5+.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see \Symfony\Component\Validator\ValidatorInterface
 * @see \Symfony\Component\Validator\Validator\ValidatorInterface
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 */
class LegacyValidator extends RecursiveValidator implements LegacyValidatorInterface
{
    public function validate($value, $groups = null, $traverse = false, $deep = false)
    {
        trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::validate method instead.', E_USER_DEPRECATED);

        return parent::validate($value, false, $groups, $traverse, $deep);
    }

    public function validateValue($value, $constraints, $groups = null)
    {
        trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::validate method instead.', E_USER_DEPRECATED);

        return parent::validate($value, $constraints, $groups);
    }

    public function getMetadataFactory()
    {
        trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::getMetadataFor or Symfony\Component\Validator\Validator\ValidatorInterface::hasMetadataFor method instead.', E_USER_DEPRECATED);

        return $this->metadataFactory;
    }
}
