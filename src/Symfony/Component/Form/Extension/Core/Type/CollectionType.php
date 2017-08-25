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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['allow_add'] && $options['prototype']) {
            $prototypeOptions = array_replace(array(
                'required' => $options['required'],
                'label' => $options['prototype_name'].'label__',
            ), $options['entry_options']);

            if (null !== $options['prototype_data']) {
                $prototypeOptions['data'] = $options['prototype_data'];
            }

            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
            $builder->setAttribute('prototype', $prototype->getForm());
        }

        $resizeListener = new ResizeFormListener(
            $options['entry_type'],
            $options['entry_options'],
            $options['allow_add'],
            $options['allow_delete'],
            $options['delete_empty']
        );

        $builder->addEventSubscriber($resizeListener);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, array(
            'allow_add' => $options['allow_add'],
            'allow_delete' => $options['allow_delete'],
        ));

        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()->getAttribute('prototype');
            $view->vars['prototype'] = $prototype->setParent($form)->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getConfig()->hasAttribute('prototype') && $view->vars['prototype']->vars['multipart']) {
            $view->vars['multipart'] = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $entryOptionsNormalizer = function (Options $options, $value) {
            $value['block_name'] = 'entry';

            return $value;
        };
        $optionsNormalizer = function (Options $options, $value) use ($entryOptionsNormalizer) {
            if (null !== $value) {
                @trigger_error('The form option "options" is deprecated since version 2.8 and will be removed in 3.0. Use "entry_options" instead.', E_USER_DEPRECATED);
            }

            return $entryOptionsNormalizer($options, $value);
        };
        $typeNormalizer = function (Options $options, $value) {
            if (null !== $value) {
                @trigger_error('The form option "type" is deprecated since version 2.8 and will be removed in 3.0. Use "entry_type" instead.', E_USER_DEPRECATED);
            }

            return $value;
        };
        $entryType = function (Options $options) {
            if (null !== $options['type']) {
                return $options['type'];
            }

            return __NAMESPACE__.'\TextType';
        };
        $entryOptions = function (Options $options) {
            if (1 === count($options['options']) && isset($options['block_name'])) {
                return array();
            }

            return $options['options'];
        };

        $resolver->setDefaults(array(
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => true,
            'prototype_data' => null,
            'prototype_name' => '__name__',
            // deprecated as of Symfony 2.8, to be removed in Symfony 3.0. Use entry_type instead
            'type' => null,
            // deprecated as of Symfony 2.8, to be removed in Symfony 3.0. Use entry_options instead
            'options' => null,
            'entry_type' => $entryType,
            'entry_options' => $entryOptions,
            'delete_empty' => false,
        ));

        $resolver->setNormalizer('type', $typeNormalizer);
        $resolver->setNormalizer('options', $optionsNormalizer);
        $resolver->setNormalizer('entry_options', $entryOptionsNormalizer);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'collection';
    }
}
