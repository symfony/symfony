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
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToSignedTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author William Pottier <developer@william-pottier.fr>
 */
class SignedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('signature_secret');

        $resolver->setDefaults(array(
            'type' => __NAMESPACE__.'\HiddenType',
            'options' => array(),
            'field_name' => 'data',
            'error_bubbling' => false,
            'signature_name' => 'signature',
            'signature_algorithm' => 'sha512',
        ));

        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('field_name', 'string');
        $resolver->setAllowedTypes('signature_secret', 'string');
        $resolver->setAllowedTypes('signature_name', 'string');
        $resolver->setAllowedTypes('signature_algorithm', 'string');
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->addViewTransformer(new ValueToSignedTransformer(
                $options['field_name'],
                $options['signature_name'],
                $options['signature_secret'],
                $options['signature_algorithm']
            ))
            ->add($options['field_name'], $options['type'], $options['options'])
            ->add($options['signature_name'], HiddenType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'signed';
    }
}