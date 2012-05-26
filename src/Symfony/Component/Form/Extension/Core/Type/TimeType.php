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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class TimeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $parts  = array('hour', 'minute');
        $format = 'H:i';
        if ($options['with_seconds']) {
            $format  = 'H:i:s';
            $parts[] = 'second';
        }

        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new DateTimeToStringTransformer($options['data_timezone'], $options['user_timezone'], $format));
        } else {
            $hourOptions = $minuteOptions = $secondOptions = array();

            if ('choice' === $options['widget']) {
                $hours = $minutes = array();

                foreach ($options['hours'] as $hour) {
                    $hours[$hour] = str_pad($hour, 2, '0', STR_PAD_LEFT);
                }
                foreach ($options['minutes'] as $minute) {
                    $minutes[$minute] = str_pad($minute, 2, '0', STR_PAD_LEFT);
                }

                // Only pass a subset of the options to children
                $hourOptions = array(
                    'choices' => $hours,
                    'empty_value' => $options['empty_value']['hour'],
                );
                $minuteOptions = array(
                    'choices' => $minutes,
                    'empty_value' => $options['empty_value']['minute'],
                );

                if ($options['with_seconds']) {
                    $seconds = array();

                    foreach ($options['seconds'] as $second) {
                        $seconds[$second] = str_pad($second, 2, '0', STR_PAD_LEFT);
                    }

                    $secondOptions = array(
                        'choices' => $seconds,
                        'empty_value' => $options['empty_value']['second'],
                    );
                }

                // Append generic carry-along options
                foreach (array('required', 'translation_domain') as $passOpt) {
                    $hourOptions[$passOpt] = $minuteOptions[$passOpt] = $options[$passOpt];
                    if ($options['with_seconds']) {
                        $secondOptions[$passOpt] = $options[$passOpt];
                    }
                }
            }

            $builder
                ->add('hour', $options['widget'], $hourOptions)
                ->add('minute', $options['widget'], $minuteOptions)
            ;

            if ($options['with_seconds']) {
                $builder->add('second', $options['widget'], $secondOptions);
            }

            $builder->addViewTransformer(new DateTimeToArrayTransformer($options['data_timezone'], $options['user_timezone'], $parts, 'text' === $options['widget']));
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], 'H:i:s')
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['data_timezone'], $options['data_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['data_timezone'], $parts)
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormViewInterface $view, FormInterface $form, array $options)
    {
        $view->addVars(array(
            'widget'       => $options['widget'],
            'with_seconds' => $options['with_seconds'],
        ));

        if ('single_text' === $options['widget']) {
            $view->setVar('type', 'time');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound = function (Options $options) {
            return $options['widget'] !== 'single_text';
        };

        $emptyValueFilter = function (Options $options, $emptyValue) {
            if (is_array($emptyValue)) {
                return array_merge(
                    array('hour' => null, 'minute' => null, 'second' => null),
                    $emptyValue
                );
            }

            return array(
                'hour' => $emptyValue,
                'minute' => $emptyValue,
                'second' => $emptyValue
            );
        };

        $resolver->setDefaults(array(
            'hours'          => range(0, 23),
            'minutes'        => range(0, 59),
            'seconds'        => range(0, 59),
            'widget'         => 'choice',
            'input'          => 'datetime',
            'with_seconds'   => false,
            'data_timezone'  => null,
            'user_timezone'  => null,
            'empty_value'    => null,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference'   => false,
            'error_bubbling' => false,
            // If initialized with a \DateTime object, FormType initializes
            // this option to "\DateTime". Since the internal, normalized
            // representation is not \DateTime, but an array, we need to unset
            // this option.
            'data_class'     => null,
            'compound'       => $compound,
        ));

        $resolver->setFilters(array(
            'empty_value' => $emptyValueFilter,
        ));

        $resolver->setAllowedValues(array(
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
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'field';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'time';
    }
}
