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

class HiddenType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            // hidden fields cannot have a required attribute
            'required'       => false,
            // Pass errors to the parent
            'error_bubbling' => true,
            'compound'       => false,
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
