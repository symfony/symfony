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
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class TimeType extends AbstractType
{
    private static $allowedSingleWidgets = array(
        'single_text',
        'text',
        'choice'
    );

    private static $allowedPartWidgets = array(
        'text',
        'choice',
    );

    private static $allowedParts = array(
        'hour',
        'minute',
        'second',
    );

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
            $builder->addViewTransformer(new DateTimeToStringTransformer($options['model_timezone'], $options['view_timezone'], $format));
        } else {
            $hourOptions = $minuteOptions = $secondOptions = array(
                'error_bubbling' => true,
            );

            if ('choice' === $options['widget']['hour']) {
                $hours = array();

                foreach ($options['hours'] as $hour) {
                    $hours[$hour] = str_pad($hour, 2, '0', STR_PAD_LEFT);
                }

                $hourOptions = array_merge($options['hour_options'], $hourOptions);
                $hourOptions['choices'] = $hours;
                $hourOptions['empty_value'] = $options['empty_value']['hour'];
            }

            if ('choice' === $options['widget']['minute']) {
                $minutes = array();

                foreach ($options['minutes'] as $minute) {
                    $minutes[$minute] = str_pad($minute, 2, '0', STR_PAD_LEFT);
                }

                $minuteOptions = array_merge($options['minute_options'], $minuteOptions);
                $minuteOptions['choices'] = $minutes;
                $minuteOptions['empty_value'] = $options['empty_value']['minute'];
            }

            if ('choice' === $options['widget']['second'] && $options['with_seconds']) {
                $seconds = array();

                foreach ($options['seconds'] as $second) {
                    $seconds[$second] = str_pad($second, 2, '0', STR_PAD_LEFT);
                }

                $secondOptions = array_merge($options['second_options'], $secondOptions);
                $secondOptions['choices'] = $seconds;
                $secondOptions['empty_value'] = $options['empty_value']['second'];
            }

            // Append generic carry-along options
            foreach (array('required', 'translation_domain') as $passOpt) {
                $hourOptions[$passOpt] = $minuteOptions[$passOpt] = $options[$passOpt];
                if ($options['with_seconds']) {
                    $secondOptions[$passOpt] = $options[$passOpt];
                }
            }

            $builder
                ->add('hour', $options['widget']['hour'], $hourOptions)
                ->add('minute', $options['widget']['minute'], $minuteOptions)
            ;

            if ($options['with_seconds']) {
                $builder->add('second', $options['widget']['second'], $secondOptions);
            }

            $builder->addViewTransformer(new DateTimeToArrayTransformer($options['model_timezone'], $options['view_timezone'], $parts, 'text' === $options['widget']));
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], 'H:i:s')
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['model_timezone'], $options['model_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['model_timezone'], $options['model_timezone'], $parts)
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, array(
            'widget'       => $options['widget'],
            'with_seconds' => $options['with_seconds'],
        ));

        if ('single_text' === $options['widget']) {
            $view->vars['type'] = 'time';
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

        $widgetNormalizer = function (Options $options, $widget) {
            if ("single_text" === $widget) {
                return $widget;
            }

            if (is_array($widget)) {
                if (0 < count(array_diff(array_keys($widget), self::$allowedParts))) {
                    throw new InvalidOptionsException(sprintf('The "widget" option can only be used to define the ' .
                        'following time parts: "%s"', implode('", "', self::$allowedParts)));

                }

                if (0 < count(array_diff($widget, self::$allowedPartWidgets))) {
                    throw new InvalidOptionsException(sprintf(
                        'The "widget" option time part widgets can only be one of "%s"',
                        implode('", "', self::$allowedPartWidgets)
                    ));
                }

                if (isset($widget["second"]) && false === $options['with_seconds']) {
                    throw new InvalidOptionsException(sprintf(
                        'The "widget" option for time part "second" cannot be set because the option "with_seconds" is '
                        . 'not enabled',
                        implode('", "', self::$allowedPartWidgets)
                    ));
                }

                return array_merge(array(
                    'hour'   => 'choice',
                    'minute' => 'choice',
                    'second' => 'choice',
                ), $widget);
            }

            if (!in_array($widget, self::$allowedSingleWidgets, true)) {
                throw new InvalidOptionsException(sprintf('The "widget" option must be one of "%s" or individually'
                    . ' defined for each time part ("%s")', implode('", "', self::$allowedSingleWidgets),
                    implode('", "', self::$allowedParts)));
            }

            return array(
                'hour'   => $widget,
                'minute' => $widget,
                'second' => $widget,
            );
        };

        $emptyValue = $emptyValueDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $emptyValueNormalizer = function (Options $options, $emptyValue) use ($emptyValueDefault) {
            if (is_array($emptyValue)) {
                $default = $emptyValueDefault($options);

                return array_merge(
                    array('hour' => $default, 'minute' => $default, 'second' => $default),
                    $emptyValue
                );
            }

            return array(
                'hour'   => $emptyValue,
                'minute' => $emptyValue,
                'second' => $emptyValue
            );
        };

        // BC until Symfony 2.3
        $modelTimezone = function (Options $options) {
            return $options['data_timezone'];
        };

        // BC until Symfony 2.3
        $viewTimezone = function (Options $options) {
            return $options['user_timezone'];
        };

        $resolver->setDefaults(array(
            'hours'          => range(0, 23),
            'minutes'        => range(0, 59),
            'seconds'        => range(0, 59),
            'hour_options'   => array(),
            'minute_options' => array(),
            'second_options' => array(),
            'widget'         => 'choice',
            'input'          => 'datetime',
            'with_seconds'   => false,
            'model_timezone' => $modelTimezone,
            'view_timezone'  => $viewTimezone,
            // Deprecated timezone options
            'data_timezone'  => null,
            'user_timezone'  => null,
            'empty_value'    => $emptyValue,
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

        $resolver->setNormalizers(array(
            'widget'      => $widgetNormalizer,
            'empty_value' => $emptyValueNormalizer,
        ));

        $resolver->setAllowedValues(array(
            'input' => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            )
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
