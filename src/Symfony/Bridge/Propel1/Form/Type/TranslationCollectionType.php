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
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
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
        if (!isset($options['options']['data_class']) || null === $options['options']['data_class']) {
            throw new MissingOptionsException('data_class must be set');
        }
        if (!isset($options['options']['columns']) || null === $options['options']['columns']) {
            throw new MissingOptionsException('columns must be set');
        }

        $listener = new TranslationCollectionFormListener($options['languages'], $options['options']['data_class']);
        $builder->addEventSubscriber($listener);
    }

    public function getParent()
    {
        return 'collection';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'propel1_translation_collection';
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(array(
            'languages',
        ));

        $resolver->setDefaults(array(
            'type' => 'propel1_translation',
            'allow_add' => false,
            'allow_delete' => false,
            'options' => array(
                'data_class' => null,
                'columns' => null,
            ),
        ));
    }
}
