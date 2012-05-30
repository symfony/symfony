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
use Symfony\Component\Form\Exception\CreationException;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class DateType extends AbstractType
{
    const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $format = $options['format'];
        $pattern = null;

        $allowedFormats = array(
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
        );

        // If $format is not in the allowed options, it's considered as the pattern of the formatter if it is a string
        if (!in_array($format, $allowedFormats, true)) {
            if (is_string($format)) {
                $format = self::DEFAULT_FORMAT;
                $pattern = $options['format'];
            } else {
                throw new CreationException('The "format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom pattern');
            }
        }

        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            $format,
            \IntlDateFormatter::NONE,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );
        $formatter->setLenient(false);

        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new DateTimeToLocalizedStringTransformer($options['data_timezone'], $options['user_timezone'], $format, \IntlDateFormatter::NONE, \IntlDateFormatter::GREGORIAN, $pattern));
        } else {
            $yearOptions = $monthOptions = $dayOptions = array();

            if ('choice' === $options['widget']) {
                if (is_array($options['empty_value'])) {
                    $options['empty_value'] = array_merge(array('year' => null, 'month' => null, 'day' => null), $options['empty_value']);
                } else {
                    $options['empty_value'] = array('year' => $options['empty_value'], 'month' => $options['empty_value'], 'day' => $options['empty_value']);
                }

                $years = $months = $days = array();

                foreach ($options['years'] as $year) {
                    $years[$year] = str_pad($year, 4, '0', STR_PAD_LEFT);
                }
                foreach ($options['months'] as $month) {
                    $months[$month] = str_pad($month, 2, '0', STR_PAD_LEFT);
                }
                foreach ($options['days'] as $day) {
                    $days[$day] = str_pad($day, 2, '0', STR_PAD_LEFT);
                }

                // Only pass a subset of the options to children
                $yearOptions = array(
                    'choices' => $years,
                    'empty_value' => $options['empty_value']['year'],
                );
                $monthOptions = array(
                    'choices' => $this->formatMonths($formatter, $months),
                    'empty_value' => $options['empty_value']['month'],
                );
                $dayOptions = array(
                    'choices' => $days,
                    'empty_value' => $options['empty_value']['day'],
                );

                // Append generic carry-along options
                foreach (array('required', 'translation_domain') as $passOpt) {
                    $yearOptions[$passOpt] = $monthOptions[$passOpt] = $dayOptions[$passOpt] = $options[$passOpt];
                }
            }

            $builder
                ->add('year', $options['widget'], $yearOptions)
                ->add('month', $options['widget'], $monthOptions)
                ->add('day', $options['widget'], $dayOptions)
                ->addViewTransformer(new DateTimeToArrayTransformer(
                    $options['data_timezone'], $options['user_timezone'], array('year', 'month', 'day')
                ))
            ;
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], 'Y-m-d')
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['data_timezone'], $options['data_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['data_timezone'], array('year', 'month', 'day'))
            ));
        }

        $builder->setAttribute('formatter', $formatter);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormViewInterface $view, FormInterface $form, array $options)
    {
        $view->setVar('widget', $options['widget']);

        if ('single_text' === $options['widget']) {
            $view->setVar('type', 'date');
        }

        if (count($view) > 0) {
            $pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+).+([yMd]+).+([yMd]+)$/', $pattern)) {
                $pattern = preg_replace(array('/y+/', '/M+/', '/d+/'), array('{{ year }}', '{{ month }}', '{{ day }}'), $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}-{{ month }}-{{ day }}';
            }

            $view->setVar('date_pattern', $pattern);
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

        $resolver->setDefaults(array(
            'years'          => range(date('Y') - 5, date('Y') + 5),
            'months'         => range(1, 12),
            'days'           => range(1, 31),
            'widget'         => 'choice',
            'input'          => 'datetime',
            'format'         => self::DEFAULT_FORMAT,
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

        $resolver->setAllowedValues(array(
            'input'     => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            ),
            'widget'    => array(
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
        return 'date';
    }

    private function formatMonths(\IntlDateFormatter $formatter, array $months)
    {
        $pattern = $formatter->getPattern();
        $timezone = $formatter->getTimezoneId();

        $formatter->setTimezoneId(\DateTimeZone::UTC);

        if (preg_match('/M+/', $pattern, $matches)) {
            $formatter->setPattern($matches[0]);

            foreach ($months as $key => $value) {
                $months[$key] = $formatter->format(gmmktime(0, 0, 0, $key, 15));
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);
        }

        $formatter->setTimezoneId($timezone);

        return $months;
    }
}
