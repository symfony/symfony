<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

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
    public function setDefaultOptions(OptionsResolverInterface $resolver)
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

            return (array) $groups;
        };

        $resolver->setDefaults(array(
            'validation_groups' => null,
        ));

        $resolver->setNormalizers(array(
            'validation_groups' => $validationGroupsNormalizer,
        ));
    }
}
