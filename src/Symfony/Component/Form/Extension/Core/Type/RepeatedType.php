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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepeatedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Overwrite required option for child fields
        $options['first_options']['required'] = $options['required'];
        $options['second_options']['required'] = $options['required'];

        if (!isset($options['options']['error_bubbling'])) {
            $options['options']['error_bubbling'] = $options['error_bubbling'];
        }

        $submittedData = [];

        $builder
            ->addViewTransformer(new ValueToDuplicatesTransformer([
                $options['first_name'],
                $options['second_name'],
            ]))
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$submittedData) {
                $submittedData = $event->getData();
            })
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options, &$submittedData) {
                $isEmpty = function (array $data, $key) {
                    return !isset($data[$key]) || '' === $data[$key] || false === $data[$key] || [] === $data[$key];
                };

                if ($isEmpty($submittedData, $options['first_name']) && !$isEmpty($submittedData, $options['second_name'])) {
                    throw new TransformationFailedException(sprintf('The key "%s" should not be empty.', $options['first_name']));
                }

                if (!$isEmpty($submittedData, $options['first_name']) && $isEmpty($submittedData, $options['second_name'])) {
                    throw new TransformationFailedException(sprintf('The key "%s" should not be empty.', $options['second_name']));
                }

                if ($submittedData[$options['first_name']] !== $submittedData[$options['second_name']]) {
                    throw new TransformationFailedException('All values in the array should be the same.');
                }
            })
            ->add($options['first_name'], $options['type'], array_merge($options['options'], $options['first_options']))
            ->add($options['second_name'], $options['type'], array_merge($options['options'], $options['second_options']))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'type' => TextType::class,
            'options' => [],
            'first_options' => [],
            'second_options' => [],
            'first_name' => 'first',
            'second_name' => 'second',
            'error_bubbling' => false,
        ]);

        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('first_options', 'array');
        $resolver->setAllowedTypes('second_options', 'array');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'repeated';
    }
}
