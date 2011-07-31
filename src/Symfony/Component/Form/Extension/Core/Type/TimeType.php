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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\FormView;

class TimeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $parts  = array('hour', 'minute');
        $format = 'H:i:00';
        if ($options['with_seconds']) {
            $format  = 'H:i:s';
            $parts[] = 'second';
        }

        if ($options['widget'] === 'single_text') {
            $builder->appendClientTransformer(new DateTimeToStringTransformer($options['data_timezone'], $options['user_timezone'], $format));
        } else {
            $hourOptions = $minuteOptions = $secondOptions = array();

            if ($options['widget'] === 'choice') {
                if (is_array($options['empty_value'])) {
                    $options['empty_value'] = array_merge(array('hour' => null, 'minute' => null, 'second' => null), $options['empty_value']);
                } else {
                    $options['empty_value'] = array('hour' => $options['empty_value'], 'minute' => $options['empty_value'], 'second' => $options['empty_value']);
                }

                // Only pass a subset of the options to children
                $hourOptions = array(
                    'choice_list' => new PaddedChoiceList(
                        array_combine($options['hours'], $options['hours']), 2, '0', STR_PAD_LEFT
                    ),
                    'empty_value' => $options['empty_value']['hour'],
                    'required' => $options['required'],
                );
                $minuteOptions = array(
                    'choice_list' => new PaddedChoiceList(
                        array_combine($options['minutes'], $options['minutes']), 2, '0', STR_PAD_LEFT
                    ),
                    'empty_value' => $options['empty_value']['minute'],
                    'required' => $options['required'],
                );

                if ($options['with_seconds']) {
                    $secondOptions = array(
                        'choice_list' => new PaddedChoiceList(
                            array_combine($options['seconds'], $options['seconds']), 2, '0', STR_PAD_LEFT
                        ),
                        'empty_value' => $options['empty_value']['second'],
                        'required' => $options['required'],
                    );
                }
            }

            $builder
                ->add('hour', $options['widget'], $hourOptions)
                ->add('minute', $options['widget'], $minuteOptions)
            ;

            if ($options['with_seconds']) {
                $builder->add('second', $options['widget'], $secondOptions);
            }

            $builder->appendClientTransformer(new DateTimeToArrayTransformer($options['data_timezone'], $options['user_timezone'], $parts, $options['widget'] === 'text'));
        }

        if ($options['input'] === 'string') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], $format)
            ));
        } else if ($options['input'] === 'timestamp') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['data_timezone'], $options['data_timezone'])
            ));
        } else if ($options['input'] === 'array') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['data_timezone'], $parts)
            ));
        }

        $builder
            ->setAttribute('widget', $options['widget'])
            ->setAttribute('with_seconds', $options['with_seconds'])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form)
    {
        $view
            ->set('widget', $form->getAttribute('widget'))
            ->set('with_seconds', $form->getAttribute('with_seconds'))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'hours'         => range(0, 23),
            'minutes'       => range(0, 59),
            'seconds'       => range(0, 59),
            'widget'        => 'choice',
            'input'         => 'datetime',
            'with_seconds'  => false,
            'data_timezone' => null,
            'user_timezone' => null,
            'empty_value'   => null,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference'  => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedOptionValues(array $options)
    {
        return array(
            'input' => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            ),
            'widget' => array(
                'single_text',
                'text',
                'choice',
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return $options['widget'] === 'single_text' ? 'field' : 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'time';
    }
}
