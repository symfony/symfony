<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\EventListener\ResizeFormListener;

class CollectionFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        if ($options['modifiable']) {
            $field->add($options['prototype'], '$$key$$', array(
                'property_path' => null,
                'required' => false,
            ));
        }

        $field->addEventListener(new ResizeFormListener($field,
                $options['prototype'], $options['modifiable']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'collection',
            'prototype' => null,
            'modifiable' => false,
            'prototype' => 'text',
        );
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getIdentifier()
    {
        return 'collection';
    }
}