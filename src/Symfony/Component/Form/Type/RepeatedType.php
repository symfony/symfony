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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\DataTransformer\ValueToDuplicatesTransformer;

class RepeatedType extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->setClientTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_name'],
                $options['second_name'],
            )))
            ->add($options['first_name'], $options['type'], $options['options'])
            ->add($options['second_name'], $options['type'], $options['options']);
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

    public function getName()
    {
        return 'repeated';
    }
}