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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;

class CollectionType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['allow_add'] && $options['prototype']) {
            $builder->add('$$name$$', $options['type'], array_replace(array(
                'property_path' => false,
                'required'      => false,
            ), $options['options']));
        }

        $listener = new ResizeFormListener(
            $builder->getFormFactory(),
            $options['type'],
            $options['options'],
            $options['allow_add'],
            $options['allow_delete']
        );

        $builder
            ->addEventSubscriber($listener)
            ->setAttribute('allow_add', $options['allow_add'])
            ->setAttribute('allow_delete', $options['allow_delete'])
        ;
    }

    public function buildView(FormView $view, FormInterface $form)
    {
        $view
            ->set('allow_add', $form->getAttribute('allow_add'))
            ->set('allow_delete', $form->getAttribute('allow_delete'))
        ;
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'allow_add'     => false,
            'allow_delete'  => false,
            'prototype'     => true,
            'type'          => 'text',
            'options'       => array(),
        );
    }

    public function getName()
    {
        return 'collection';
    }
}