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
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['allow_add'] && $options['prototype']) {
            $prototypeOptions = array_replace([
                'required' => $options['required'],
                'label' => $options['prototype_name'].'label__',
            ], array_replace($options['entry_options'], $options['prototype_options']));

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

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'allow_add' => $options['allow_add'],
            'allow_delete' => $options['allow_delete'],
        ]);

        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()->getAttribute('prototype');
            $view->vars['prototype'] = $prototype->setParent($form)->createView($view);
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $prefixOffset = -2;
        // check if the entry type also defines a block prefix
        /** @var FormInterface $entry */
        foreach ($form as $entry) {
            if ($entry->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            break;
        }

        foreach ($view as $entryView) {
            array_splice($entryView->vars['block_prefixes'], $prefixOffset, 0, 'collection_entry');
        }

        /** @var FormInterface $prototype */
        if ($prototype = $form->getConfig()->getAttribute('prototype')) {
            if ($view->vars['prototype']->vars['multipart']) {
                $view->vars['multipart'] = true;
            }

            if ($prefixOffset > -3 && $prototype->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            array_splice($view->vars['prototype']->vars['block_prefixes'], $prefixOffset, 0, 'collection_entry');
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $entryOptionsNormalizer = function (Options $options, $value) {
            $value['block_name'] = 'entry';

            return $value;
        };

        $resolver->setDefaults([
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => true,
            'prototype_data' => null,
            'prototype_name' => '__name__',
            'entry_type' => TextType::class,
            'entry_options' => [],
            'prototype_options' => [],
            'delete_empty' => false,
            'invalid_message' => 'The collection is invalid.',
        ]);

        $resolver->setNormalizer('entry_options', $entryOptionsNormalizer);

        $resolver->setAllowedTypes('delete_empty', ['bool', 'callable']);
        $resolver->setAllowedTypes('prototype_options', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'collection';
    }
}
