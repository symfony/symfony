<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\Type;

use Symphony\Component\Form\AbstractTypeExtension;
use Symphony\Component\OptionsResolver\Options;
use Symphony\Component\OptionsResolver\OptionsResolver;
use Symphony\Component\Validator\Constraints\GroupSequence;

/**
 * Encapsulates common logic of {@link FormTypeValidatorExtension} and
 * {@link SubmitTypeValidatorExtension}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class BaseValidatorExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        // Make sure that validation groups end up as null, closure or array
        $validationGroupsNormalizer = function (Options $options, $groups) {
            if (false === $groups) {
                return array();
            }

            if (empty($groups)) {
                return;
            }

            if (is_callable($groups)) {
                return $groups;
            }

            if ($groups instanceof GroupSequence) {
                return $groups;
            }

            return (array) $groups;
        };

        $resolver->setDefaults(array(
            'validation_groups' => null,
        ));

        $resolver->setNormalizer('validation_groups', $validationGroupsNormalizer);
    }
}
