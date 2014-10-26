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
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToRfc3339Transformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class DateTimeType extends AbstractType
{
    const DEFAULT_DATE_FORMAT = \IntlDateFormatter::MEDIUM;

    const DEFAULT_TIME_FORMAT = \IntlDateFormatter::MEDIUM;

    /**
     * This is not quite the HTML5 format yet, because ICU lacks the
     * capability of parsing and generating RFC 3339 dates, which
     * are like the below pattern but with a timezone suffix. The
     * timezone suffix is
     *
     *  * "Z" for UTC
     *  * "(-|+)HH:mm" for other timezones (note the colon!)
     *
     * For more information see:
     *
     * http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     * http://www.w3.org/TR/html-markup/input.datetime.html
     * http://tools.ietf.org/html/rfc3339
     *
     * An ICU ticket was created:
     * http://icu-project.org/trac/ticket/9421
     *
     * It was supposedly fixed, but is not available in all PHP installations
     * yet. To temporarily circumvent this issue, DateTimeToRfc3339Transformer
     * is used when the format matches this constant.
     */
    const HTML5_FORMAT = "yyyy-MM-dd'T'HH:mm:ssZZZZZ";

    private static $acceptedFormats = array(
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $parts = array('year', 'month', 'day', 'hour');
        $dateParts = array('year', 'month', 'day');
        $timeParts = array('hour');

        if ($options['with_minutes']) {
            $parts[] = 'minute';
            $timeParts[] = 'minute';
        }

        if ($options['with_seconds']) {
            $parts[] = 'second';
            $timeParts[] = 'second';
        }

        $dateFormat = is_int($options['date_format']) ? $options['date_format'] : self::DEFAULT_DATE_FORMAT;
        $timeFormat = self::DEFAULT_TIME_FORMAT;
        $calendar = \IntlDateFormatter::GREGORIAN;
        $pattern = is_string($options['format']) ? $options['format'] : null;

        if (!in_array($dateFormat, self::$acceptedFormats, true)) {
            throw new InvalidOptionsException('The "date_format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
        }

        if ('single_text' === $options['widget']) {
            if (self::HTML5_FORMAT === $pattern) {
                $builder->addViewTransformer(new DateTimeToRfc3339Transformer(
                    $options['model_timezone'],
                    $options['view_timezone']
                ));
            } else {
                $builder->addViewTransformer(new DateTimeToLocalizedStringTransformer(
                    $options['model_timezone'],
                    $options['view_timezone'],
                    $dateFormat,
                    $timeFormat,
                    $calendar,
                    $pattern
                ));
            }
        } else {
            // Only pass a subset of the options to children
            $dateOptions = array_intersect_key($options, array_flip(array(
                'years',
                'months',
                'days',
                'empty_value',
                'required',
                'translation_domain',
                'invalid_message',
                'invalid_message_parameters',
            )));

            $timeOptions = array_intersect_key($options, array_flip(array(
                'hours',
                'minutes',
                'seconds',
                'with_minutes',
                'with_seconds',
                'empty_value',
                'required',
                'translation_domain',
                'invalid_message',
                'invalid_message_parameters',
            )));

            if (null !== $options['date_widget']) {
                $dateOptions['widget'] = $options['date_widget'];
            }

            if (null !== $options['time_widget']) {
                $timeOptions['widget'] = $options['time_widget'];
            }

            if (null !== $options['date_format']) {
                $dateOptions['format'] = $options['date_format'];
            }

            $dateOptions['input'] = $timeOptions['input'] = 'array';
            $dateOptions['error_bubbling'] = $timeOptions['error_bubbling'] = true;

            $builder
                ->addViewTransformer(new DataTransformerChain(array(
                    new DateTimeToArrayTransformer($options['model_timezone'], $options['view_timezone'], $parts),
                    new ArrayToPartsTransformer(array(
                        'date' => $dateParts,
                        'time' => $timeParts,
                    )),
                )))
                ->add('date', 'date', $dateOptions)
                ->add('time', 'time', $timeOptions)
            ;
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'])
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
        $view->vars['widget'] = $options['widget'];

        // Change the input to a HTML5 date input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        if ('single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'datetime';
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

        // Defaults to the value of "widget"
        $dateWidget = function (Options $options) {
            return $options['widget'];
        };

        // Defaults to the value of "widget"
        $timeWidget = function (Options $options) {
            return $options['widget'];
        };

        $resolver->setDefaults(array(
            'input' => 'datetime',
            'model_timezone' => null,
            'view_timezone' => null,
            'format' => self::HTML5_FORMAT,
            'date_format' => null,
            'widget' => null,
            'date_widget' => $dateWidget,
            'time_widget' => $timeWidget,
            'with_minutes' => true,
            'with_seconds' => false,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
            'error_bubbling' => false,
            // If initialized with a \DateTime object, FormType initializes
            // this option to "\DateTime". Since the internal, normalized
            // representation is not \DateTime, but an array, we need to unset
            // this option.
            'data_class' => null,
            'compound' => $compound,
        ));

        // Don't add some defaults in order to preserve the defaults
        // set in DateType and TimeType
        $resolver->setOptional(array(
            'empty_value',
            'years',
            'months',
            'days',
            'hours',
            'minutes',
            'seconds',
        ));

        $resolver->setAllowedValues(array(
            'input' => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            ),
            'date_widget' => array(
                null, // inherit default from DateType
                'single_text',
                'text',
                'choice',
            ),
            'time_widget' => array(
                null, // inherit default from TimeType
                'single_text',
                'text',
                'choice',
            ),
            // This option will overwrite "date_widget" and "time_widget" options
            'widget' => array(
                null, // default, don't overwrite options
                'single_text',
                'text',
                'choice',
            ),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'datetime';
    }
}
