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

/**
 * Class TelType
 *
 * @package Symfony\Component\Form\Extension\Core\Type
 */
class TelType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'placeholder'   => '',
            'size'          => 10,
            'required'      => true,
            'readonly'      => '',
            'pattern'       => '',
            'maxlength'     => 10,
            'autofocus'     => '',
            'autocomplete'  => 'on'
        ));

        $resolver->setAllowedValues(array(
            'autocomplete'  => array('on', 'off'),
            'required'      => array(true, false),
            'readonly'      => array('readonly', '', null),
            'autofocus'     => array('autofocus', '', null)
        ));

        $resolver->setAllowedTypes(array(
            'placeholder'   => array('string'),
            'size'          => array('integer'),
            'pattern'       => array('regex', 'string'),
            'maxlength'     => array('integer')
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'text';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'tel';
    }
}
