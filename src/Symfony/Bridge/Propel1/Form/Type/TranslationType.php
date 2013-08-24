<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Bridge\Propel1\Form\EventListener\TranslationFormListener;

/**
 * Translation type class
 *
 * @author Patrick Kaufmann
 *
 * @since v2.2.0
 */
class TranslationType extends AbstractType
{
    /**
      * {@inheritdoc}
     *
     * @since v2.2.0
      */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(
            new TranslationFormListener($options['columns'], $options['data_class'])
        );
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function getName()
    {
        return 'propel1_translation';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(array(
            'data_class',
            'columns'
        ));
    }
}
