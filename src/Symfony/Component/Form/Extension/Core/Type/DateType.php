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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class DateType extends AbstractType
{
    const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;

    const HTML5_FORMAT = 'yyyy-MM-dd';

    private static $acceptedFormats = array(
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    );

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
        'year',
        'month',
        'day',
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dateFormat = is_int($options['format']) ? $options['format'] : self::DEFAULT_FORMAT;
        $timeFormat = \IntlDateFormatter::NONE;
        $calendar = \IntlDateFormatter::GREGORIAN;
        $pattern = is_string($options['format']) ? $options['format'] : null;

        if (!in_array($dateFormat, self::$acceptedFormats, true)) {
            throw new InvalidOptionsException('The "format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
        }

        if (null !== $pattern && (false === strpos($pattern, 'y') || false === strpos($pattern, 'M') || false === strpos($pattern, 'd'))) {
            throw new InvalidOptionsException(sprintf('The "format" option should contain the letters "y", "M" and "d". Its current value is "%s".', $pattern));
        }

        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new DateTimeToLocalizedStringTransformer(
                $options['model_timezone'],
                $options['view_timezone'],
                $dateFormat,
                $timeFormat,
                $calendar,
                $pattern
            ));
        } else {
            $yearOptions = $monthOptions = $dayOptions = array(
                'error_bubbling' => true,
            );

            $formatter = new \IntlDateFormatter(
                \Locale::getDefault(),
                $dateFormat,
                $timeFormat,
                'UTC',
                $calendar,
                $pattern
            );
            $formatter->setLenient(false);

            if ('choice' === $options['widget']['year']) {
                // Only pass a subset of the options to children
                $yearOptions = array_merge($options['year_options'], $yearOptions);
                $yearOptions['choices'] = $this->formatTimestamps($formatter, '/y+/', $this->listYears($options['years']));
                $yearOptions['empty_value'] = $options['empty_value']['year'];
            }

            if ('choice' === $options['widget']['month']) {
                $monthOptions = array_merge($options['month_options'], $monthOptions);
                $monthOptions['choices'] = $this->formatTimestamps($formatter, '/M+/', $this->listMonths($options['months']));
                $monthOptions['empty_value'] = $options['empty_value']['month'];
            }

            if ('choice' === $options['widget']['day']) {
                $dayOptions = array_merge($options['day_options'], $dayOptions);
                $dayOptions['choices'] = $this->formatTimestamps($formatter, '/d+/', $this->listDays($options['days']));
                $dayOptions['empty_value'] = $options['empty_value']['day'];
            }

            // Append generic carry-along options
            foreach (array('required', 'translation_domain') as $passOpt) {
                $yearOptions[$passOpt] = $monthOptions[$passOpt] = $dayOptions[$passOpt] = $options[$passOpt];
            }

            $builder
                ->add('year', $options['widget']['year'], $yearOptions)
                ->add('month', $options['widget']['month'], $monthOptions)
                ->add('day', $options['widget']['day'], $dayOptions)
                ->addViewTransformer(new DateTimeToArrayTransformer(
                    $options['model_timezone'], $options['view_timezone'], array('year', 'month', 'day')
                ))
                ->setAttribute('formatter', $formatter)
            ;
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], 'Y-m-d')
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['model_timezone'], $options['model_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['model_timezone'], $options['model_timezone'], array('year', 'month', 'day'))
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['widget'] = $options['widget'];

        // Change the input to a HTML5 date input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        if ('single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'date';
        }

        if ($form->getConfig()->hasAttribute('formatter')) {
            $pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+).+([yMd]+).+([yMd]+)$/', $pattern)) {
                $pattern = preg_replace(array('/y+/', '/M+/', '/d+/'), array('{{ year }}', '{{ month }}', '{{ day }}'), $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}-{{ month }}-{{ day }}';
            }

            $view->vars['date_pattern'] = $pattern;
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
                        'following date parts: "%s"', implode('", "', self::$allowedParts)));

                }

                if (0 < count(array_diff($widget, self::$allowedPartWidgets))) {
                    throw new InvalidOptionsException(sprintf(
                        'The "widget" option date part widgets can only be one of "%s"',
                        implode('", "', self::$allowedPartWidgets)
                    ));
                }

                return array_merge(array(
                    'year'  => 'choice',
                    'month' => 'choice',
                    'day'   => 'choice',
                ), $widget);
            }

            if (!in_array($widget, self::$allowedSingleWidgets, true)) {
                throw new InvalidOptionsException(sprintf('The "widget" option must be one of "%s" or individually'
                    . ' defined for each date part ("%s")', implode('", "', self::$allowedSingleWidgets),
                    implode('", "', self::$allowedParts)));
            }

            return array(
                'year'  => $widget,
                'month' => $widget,
                'day'   => $widget,
            );
        };

        $emptyValue = $emptyValueDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $emptyValueNormalizer = function (Options $options, $emptyValue) use ($emptyValueDefault) {
            if (is_array($emptyValue)) {
                $default = $emptyValueDefault($options);

                return array_merge(
                    array('year' => $default, 'month' => $default, 'day' => $default),
                    $emptyValue
                );
            }

            return array(
                'year' => $emptyValue,
                'month' => $emptyValue,
                'day' => $emptyValue
            );
        };

        $format = function (Options $options) {
            return $options['widget'] === 'single_text' ? DateType::HTML5_FORMAT : DateType::DEFAULT_FORMAT;
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
            'years'          => range(date('Y') - 5, date('Y') + 5),
            'months'         => range(1, 12),
            'days'           => range(1, 31),
            'year_options'   => array(),
            'month_options'  => array(),
            'day_options'    => array(),
            'widget'         => 'choice',
            'input'          => 'datetime',
            'format'         => $format,
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
            'input'     => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            )
        ));

        $resolver->setAllowedTypes(array(
            'format' => array('int', 'string'),
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
        return 'date';
    }

    private function formatTimestamps(\IntlDateFormatter $formatter, $regex, array $timestamps)
    {
        $pattern = $formatter->getPattern();
        $timezone = $formatter->getTimezoneId();

        $formatter->setTimezoneId(\DateTimeZone::UTC);

        if (preg_match($regex, $pattern, $matches)) {
            $formatter->setPattern($matches[0]);

            foreach ($timestamps as $key => $timestamp) {
                $timestamps[$key] = $formatter->format($timestamp);
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);
        }

        $formatter->setTimezoneId($timezone);

        return $timestamps;
    }

    private function listYears(array $years)
    {
        $result = array();

        foreach ($years as $year) {
            $result[$year] = gmmktime(0, 0, 0, 6, 15, $year);
        }

        return $result;
    }

    private function listMonths(array $months)
    {
        $result = array();

        foreach ($months as $month) {
            $result[$month] = gmmktime(0, 0, 0, $month, 15);
        }

        return $result;
    }

    private function listDays(array $days)
    {
        $result = array();

        foreach ($days as $day) {
            $result[$day] = gmmktime(0, 0, 0, 5, $day);
        }

        return $result;
    }
}
