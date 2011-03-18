<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\DataTransformer\ValueToDuplicatesTransformer;

class RepeatedFieldType extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->setDataTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_name'],
                $options['second_name'],
            )))
            ->add($options['type'], $options['first_name'], $options['options'])
            ->add($options['type'], $options['second_name'], $options['options']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'repeated',
            'type' => 'text',
            'options' => array(),
            'first_name' => 'first',
            'second_name' => 'second',
            'csrf_protection' => false,
        );
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getName()
    {
        return 'repeated';
    }
}