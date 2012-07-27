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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Bridge\Propel1\Form\EventListener\TranslationCollectionFormListener;

/**
 * form type for i18n-columns in propel
 *
 * @author Patrick Kaufmann
 */
class TranslationCollectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $listener = new TranslationCollectionFormListener($options['languages'], $options['options']['data_class']);
        $builder->addEventSubscriber($listener);
    }
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'propel1_translatable_collection';
    }

    public function getParent()
    {
        return 'collection';
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'languages' => array(),
            'columns' => array(),
            'type' => 'propel1_translation',
            'allow_add' => false,
            'allow_delete' => false
        ));
    }
}
