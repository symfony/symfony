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
            $prototype = $builder->create($options['prototype_name'], $options['type'], array_replace(array(
                'label' => $options['prototype_name'].'label__',
            ), self::normalizeOptions($options['options'], null)));
            $builder->setAttribute('prototype', $prototype->getForm());
        }

        $resizeListener = new ResizeFormListener(
            $options['type'],
            $options['options'],
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
            $view->vars['prototype'] = $form->getConfig()->getAttribute('prototype')->createView($view);
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
        $optionsNormalizer = function (Options $options, $value) {
            // If this is a closure, a wrapped closure needs to be returned in order
            // to call the closure when needed, and still force the block_name
            if ($value instanceof \Closure) {
                return function ($data) use ($value) {
                    $options = $value($data);
                    $options['block_name'] = 'entry';

                    return $options;
                };
            } else {
                $value['block_name'] = 'entry';
            }

            return $value;
        };

        $resolver->setDefaults(array(
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => true,
            'prototype_name' => '__name__',
            'type' => 'text',
            'options' => array(),
            'delete_empty' => false,
        ));

        $resolver->setNormalizer('options', $optionsNormalizer);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'collection';
    }

    /**
     * This is a utility method used specifically by this class and its ResizeFormListener for getting dynamic options
     * for each child in this collection based off of the data provided.
     *
     * @param array|\Closure $options
     * @param $data
     * @return mixed
     */
    public static function normalizeOptions($options, $data)
    {
        if ($options instanceof \Closure) {
            return $options($data);
        }

        return $options;
    }
}
