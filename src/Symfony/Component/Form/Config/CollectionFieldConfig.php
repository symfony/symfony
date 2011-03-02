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
            $child = clone $options['prototype'];
            $child->setKey('$$key$$');
            $child->setPropertyPath(null);
            // TESTME
            $child->setRequired(false);
            $field->add($child);
        }

        $field->addEventListener(new ResizeFormListener($field,
                $options['prototype'], $options['modifiable']));
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'template' => 'collection',
            'prototype' => null,
            'modifiable' => false,
        );

        // Lazy creation of the prototype
        if (!isset($options['prototype'])) {
            $defaultOptions['prototype'] = $this->getInstance('text');
        }

        return $defaultOptions;
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