<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

class HiddenType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        //force required attribute to false
        $requiredNormalizer = function (Options $options, $required)
        {
            return false;
        };

        $resolver->setDefaults(array(
            // hidden fields cannot have a required attribute
            'required'       => false,
            // Pass errors to the parent
            'error_bubbling' => true,
            'compound'       => false,
        ));

        $resolver->setNormalizers(array(
            'required' => $requiredNormalizer,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'hidden';
    }
}
