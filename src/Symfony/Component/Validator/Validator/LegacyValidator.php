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
        if (is_array($value)) {
            return $this->validateValue($value, new Traverse(array(
                'traverse' => true,
                'deep' => $deep,
            )), $groups);
        }

        if ($traverse && $value instanceof \Traversable) {
            return $this->validateValue($value, array(
                new Valid(),
                new Traverse(array('traverse' => true, 'deep' => $deep)),
            ), $groups);
        }

        return $this->validateObject($value, $groups);
    }

    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }
}
