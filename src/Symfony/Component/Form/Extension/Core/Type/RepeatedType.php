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
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class RepeatedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->appendClientTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_name'],
                $options['second_name'],
            )))
            ->add($options['first_name'], $options['type'], $options['options'])
            ->add($options['second_name'], $options['type'], $options['options'])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'type'              => 'text',
            'options'           => array(),
            'first_name'        => 'first',
            'second_name'       => 'second',
            'error_bubbling'    => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'repeated';
    }
}
