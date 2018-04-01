<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\Type;

use Symphony\Component\Form\ButtonTypeInterface;
use Symphony\Component\OptionsResolver\OptionsResolver;

/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonType extends BaseType implements ButtonTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'button';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'auto_initialize' => false,
        ));
    }
}
