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
use Symfony\Component\OptionsResolver\OptionsResolver;
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
                null,
                $calendar,
                $pattern
            );

            // new \IntlDateFormatter may return null instead of false in case of failure, see https://bugs.php.net/bug.php?id=66323
            if (!$formatter) {
                throw new InvalidOptionsException(intl_get_error_message(), intl_get_error_code());
            }

            $formatter->setLenient(false);

            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $yearOptions['choices'] = $this->formatTimestamps($formatter, '/y+/', $this->listYears($options['years']));
                $yearOptions['choices_as_values'] = true;
                $yearOptions['placeholder'] = $options['placeholder']['year'];
                $monthOptions['choices'] = $this->formatTimestamps($formatter, '/[M|L]+/', $this->listMonths($options['months']));
                $monthOptions['choices_as_values'] = true;
                $monthOptions['placeholder'] = $options['placeholder']['month'];
                $dayOptions['choices'] = $this->formatTimestamps($formatter, '/d+/', $this->listDays($options['days']));
                $dayOptions['choices_as_values'] = true;
                $dayOptions['placeholder'] = $options['placeholder']['day'];
            }

            // Append generic carry-along options
            foreach (array('required', 'translation_domain') as $passOpt) {
                $yearOptions[$passOpt] = $monthOptions[$passOpt] = $dayOptions[$passOpt] = $options[$passOpt];
            }

            $builder
                ->add('year', $options['widget'], $yearOptions)
                ->add('month', $options['widget'], $monthOptions)
                ->add('day', $options['widget'], $dayOptions)
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
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'date';
        }

        if ($form->getConfig()->hasAttribute('formatter')) {
            $pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

            // remove special characters unless the format was explicitly specified
            if (!is_string($options['format'])) {
                // remove quoted strings first
                $pattern = preg_replace('/\'[^\']+\'/', '', $pattern);

                // remove remaining special chars
                $pattern = preg_replace('/[^yMd]+/', '', $pattern);
            }

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+)[^yMd]*([yMd]+)[^yMd]*([yMd]+)$/', $pattern)) {
                $pattern = preg_replace(array('/y+/', '/M+/', '/d+/'), array('{{ year }}', '{{ month }}', '{{ day }}'), $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}{{ month }}{{ day }}';
            }

            $view->vars['date_pattern'] = $pattern;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $compound = function (Options $options) {
            return 'single_text' !== $options['widget'];
        };

        $placeholder = $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault) {
            if (!is_object($options['empty_value']) || !$options['empty_value'] instanceof \Exception) {
                @trigger_error('The form option "empty_value" is deprecated since version 2.6 and will be removed in 3.0. Use "placeholder" instead.', E_USER_DEPRECATED);

                $placeholder = $options['empty_value'];
            }

            if (is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(
                    array('year' => $default, 'month' => $default, 'day' => $default),
                    $placeholder
                );
            }

            return array(
                'year' => $placeholder,
                'month' => $placeholder,
                'day' => $placeholder,
            );
        };

        $format = function (Options $options) {
            return 'single_text' === $options['widget'] ? DateType::HTML5_FORMAT : DateType::DEFAULT_FORMAT;
        };

        $resolver->setDefaults(array(
            'years' => range(date('Y') - 5, date('Y') + 5),
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'choice',
            'input' => 'datetime',
            'format' => $format,
            'model_timezone' => null,
            'view_timezone' => null,
            'empty_value' => new \Exception(), // deprecated
            'placeholder' => $placeholder,
            'html5' => true,
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

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);

        $resolver->setAllowedValues('input', array(
            'datetime',
            'string',
            'timestamp',
            'array',
        ));
        $resolver->setAllowedValues('widget', array(
            'single_text',
            'text',
            'choice',
        ));

        $resolver->setAllowedTypes('format', array('int', 'string'));
        $resolver->setAllowedTypes('years', 'array');
        $resolver->setAllowedTypes('months', 'array');
        $resolver->setAllowedTypes('days', 'array');
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
        $formattedTimestamps = array();

        if ($setTimeZone = PHP_VERSION_ID >= 50500 || method_exists($formatter, 'setTimeZone')) {
            $formatter->setTimeZone('UTC');
        } else {
            $formatter->setTimeZoneId('UTC');
        }

        if (preg_match($regex, $pattern, $matches)) {
            $formatter->setPattern($matches[0]);

            foreach ($timestamps as $timestamp => $choice) {
                $formattedTimestamps[$formatter->format($timestamp)] = $choice;
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);
        }

        if ($setTimeZone) {
            $formatter->setTimeZone($timezone);
        } else {
            $formatter->setTimeZoneId($timezone);
        }

        return $formattedTimestamps;
    }

    private function listYears(array $years)
    {
        $result = array();

        foreach ($years as $year) {
            if (false !== $y = gmmktime(0, 0, 0, 6, 15, $year)) {
                $result[$y] = $year;
            }
        }

        return $result;
    }

    private function listMonths(array $months)
    {
        $result = array();

        foreach ($months as $month) {
            $result[gmmktime(0, 0, 0, $month, 15)] = $month;
        }

        return $result;
    }

    private function listDays(array $days)
    {
        $result = array();

        foreach ($days as $day) {
            $result[gmmktime(0, 0, 0, 5, $day)] = $day;
        }

        return $result;
    }
}
