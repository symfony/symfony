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
use Symfony\Component\Form\ValueTransformer\ValueToDuplicatesTransformer;

class RepeatedFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $field->setValueTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_key'],
                $options['second_key'],
            )))
            ->add($this->getInstance($options['identifier'], $options['first_key'], $options['options']))
            ->add($this->getInstance($options['identifier'], $options['second_key'], $options['options']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'repeated',
            'identifier' => 'text',
            'options' => array(),
            'first_key' => 'first',
            'second_key' => 'second',
            'csrf_protection' => false,
        );
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getIdentifier()
    {
        return 'repeated';
    }
}