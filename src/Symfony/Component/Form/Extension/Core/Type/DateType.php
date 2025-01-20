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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToDateTimeTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateType extends AbstractType
{
    public const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;
    public const HTML5_FORMAT = 'yyyy-MM-dd';

    private const ACCEPTED_FORMATS = [
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    ];

    private const WIDGETS = [
        'text' => TextType::class,
        'choice' => ChoiceType::class,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dateFormat = \is_int($options['format']) ? $options['format'] : self::DEFAULT_FORMAT;
        $timeFormat = \IntlDateFormatter::NONE;
        $calendar = $options['calendar'] ?? \IntlDateFormatter::GREGORIAN;
        $pattern = \is_string($options['format']) ? $options['format'] : '';

        if (!\in_array($dateFormat, self::ACCEPTED_FORMATS, true)) {
            throw new InvalidOptionsException('The "format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
        }

        if ('single_text' === $options['widget']) {
            if ('' !== $pattern && !str_contains($pattern, 'y') && !str_contains($pattern, 'M') && !str_contains($pattern, 'd')) {
                throw new InvalidOptionsException(\sprintf('The "format" option should contain the letters "y", "M" or "d". Its current value is "%s".', $pattern));
            }

            $builder->addViewTransformer(new DateTimeToLocalizedStringTransformer(
                $options['model_timezone'],
                $options['view_timezone'],
                $dateFormat,
                $timeFormat,
                $calendar,
                $pattern
            ));
        } else {
            if ('' !== $pattern && (!str_contains($pattern, 'y') || !str_contains($pattern, 'M') || !str_contains($pattern, 'd'))) {
                throw new InvalidOptionsException(\sprintf('The "format" option should contain the letters "y", "M" and "d". Its current value is "%s".', $pattern));
            }

            $yearOptions = $monthOptions = $dayOptions = [
                'error_bubbling' => true,
                'empty_data' => '',
            ];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $emptyData = $builder->getEmptyData() ?: [];

            if ($emptyData instanceof \Closure) {
                $lazyEmptyData = static fn ($option) => static function (FormInterface $form) use ($emptyData, $option) {
                    $emptyData = $emptyData($form->getParent());

                    return $emptyData[$option] ?? '';
                };

                $yearOptions['empty_data'] = $lazyEmptyData('year');
                $monthOptions['empty_data'] = $lazyEmptyData('month');
                $dayOptions['empty_data'] = $lazyEmptyData('day');
            } else {
                if (isset($emptyData['year'])) {
                    $yearOptions['empty_data'] = $emptyData['year'];
                }
                if (isset($emptyData['month'])) {
                    $monthOptions['empty_data'] = $emptyData['month'];
                }
                if (isset($emptyData['day'])) {
                    $dayOptions['empty_data'] = $emptyData['day'];
                }
            }

            if (isset($options['invalid_message'])) {
                $dayOptions['invalid_message'] = $options['invalid_message'];
                $monthOptions['invalid_message'] = $options['invalid_message'];
                $yearOptions['invalid_message'] = $options['invalid_message'];
            }

            if (isset($options['invalid_message_parameters'])) {
                $dayOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $monthOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $yearOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }

            $formatter = new \IntlDateFormatter(
                \Locale::getDefault(),
                $dateFormat,
                $timeFormat,
                null,
                $calendar,
                $pattern
            );

            $formatter->setLenient(false);

            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $yearOptions['choices'] = $this->formatTimestamps($formatter, '/y+/', $this->listYears($options['years']));
                $yearOptions['placeholder'] = $options['placeholder']['year'];
                $yearOptions['choice_translation_domain'] = $options['choice_translation_domain']['year'];
                $monthOptions['choices'] = $this->formatTimestamps($formatter, '/[M|L]+/', $this->listMonths($options['months']));
                $monthOptions['placeholder'] = $options['placeholder']['month'];
                $monthOptions['choice_translation_domain'] = $options['choice_translation_domain']['month'];
                $dayOptions['choices'] = $this->formatTimestamps($formatter, '/d+/', $this->listDays($options['days']));
                $dayOptions['placeholder'] = $options['placeholder']['day'];
                $dayOptions['choice_translation_domain'] = $options['choice_translation_domain']['day'];
            }

            // Append generic carry-along options
            foreach (['required', 'translation_domain'] as $passOpt) {
                $yearOptions[$passOpt] = $monthOptions[$passOpt] = $dayOptions[$passOpt] = $options[$passOpt];
            }

            $builder
                ->add('year', self::WIDGETS[$options['widget']], $yearOptions)
                ->add('month', self::WIDGETS[$options['widget']], $monthOptions)
                ->add('day', self::WIDGETS[$options['widget']], $dayOptions)
                ->addViewTransformer(new DateTimeToArrayTransformer(
                    $options['model_timezone'], $options['view_timezone'], ['year', 'month', 'day']
                ))
                ->setAttribute('formatter', $formatter)
            ;
        }

        if ('datetime_immutable' === $options['input']) {
            $builder->addModelTransformer(new DateTimeImmutableToDateTimeTransformer());
        } elseif ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], $options['input_format'])
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['model_timezone'], $options['model_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['model_timezone'], $options['model_timezone'], ['year', 'month', 'day'])
            ));
        }

        if (\in_array($options['input'], ['datetime', 'datetime_immutable'], true) && null !== $options['model_timezone']) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($options): void {
                $date = $event->getData();

                if (!$date instanceof \DateTimeInterface) {
                    return;
                }

                if ($date->getTimezone()->getName() !== $options['model_timezone']) {
                    throw new LogicException(\sprintf('Using a "%s" instance with a timezone ("%s") not matching the configured model timezone "%s" is not supported.', get_debug_type($date), $date->getTimezone()->getName(), $options['model_timezone']));
                }
            });
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['widget'] = $options['widget'];

        // Change the input to an HTML5 date input if
        //  * the widget is set to "single_text"
        //  * the format matches the one expected by HTML5
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget'] && self::HTML5_FORMAT === $options['format']) {
            $view->vars['type'] = 'date';
        }

        if ($form->getConfig()->hasAttribute('formatter')) {
            $pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

            // remove special characters unless the format was explicitly specified
            if (!\is_string($options['format'])) {
                // remove quoted strings first
                $pattern = preg_replace('/\'[^\']+\'/', '', $pattern);

                // remove remaining special chars
                $pattern = preg_replace('/[^yMd]+/', '', $pattern);
            }

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+)[^yMd]*([yMd]+)[^yMd]*([yMd]+)$/', $pattern)) {
                $pattern = preg_replace(['/y+/', '/M+/', '/d+/'], ['{{ year }}', '{{ month }}', '{{ day }}'], $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}{{ month }}{{ day }}';
            }

            $view->vars['date_pattern'] = $pattern;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $compound = static fn (Options $options) => 'single_text' !== $options['widget'];

        $placeholderDefault = static fn (Options $options) => $options['required'] ? null : '';

        $placeholderNormalizer = static function (Options $options, $placeholder) use ($placeholderDefault) {
            if (\is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(
                    ['year' => $default, 'month' => $default, 'day' => $default],
                    $placeholder
                );
            }

            return [
                'year' => $placeholder,
                'month' => $placeholder,
                'day' => $placeholder,
            ];
        };

        $choiceTranslationDomainNormalizer = static function (Options $options, $choiceTranslationDomain) {
            if (\is_array($choiceTranslationDomain)) {
                return array_replace(
                    ['year' => false, 'month' => false, 'day' => false],
                    $choiceTranslationDomain
                );
            }

            return [
                'year' => $choiceTranslationDomain,
                'month' => $choiceTranslationDomain,
                'day' => $choiceTranslationDomain,
            ];
        };

        $format = static fn (Options $options) => 'single_text' === $options['widget'] ? self::HTML5_FORMAT : self::DEFAULT_FORMAT;

        $resolver->setDefaults([
            'years' => range((int) date('Y') - 5, (int) date('Y') + 5),
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'single_text',
            'input' => 'datetime',
            'format' => $format,
            'model_timezone' => null,
            'view_timezone' => null,
            'calendar' => null,
            'placeholder' => $placeholderDefault,
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
            'empty_data' => static fn (Options $options) => $options['compound'] ? [] : '',
            'choice_translation_domain' => false,
            'input_format' => 'Y-m-d',
            'invalid_message' => 'Please enter a valid date.',
        ]);

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);

        $resolver->setAllowedValues('input', [
            'datetime',
            'datetime_immutable',
            'string',
            'timestamp',
            'array',
        ]);
        $resolver->setAllowedValues('widget', [
            'single_text',
            'text',
            'choice',
        ]);

        $resolver->setAllowedTypes('format', ['int', 'string']);
        $resolver->setAllowedTypes('years', 'array');
        $resolver->setAllowedTypes('months', 'array');
        $resolver->setAllowedTypes('days', 'array');
        $resolver->setAllowedTypes('input_format', 'string');
        $resolver->setAllowedTypes('calendar', ['null', 'int', \IntlCalendar::class]);

        $resolver->setInfo('calendar', 'The calendar to use for formatting and parsing the date. The value should be an instance of \IntlCalendar. By default, the Gregorian calendar with the default locale is used.');

        $resolver->setNormalizer('html5', static function (Options $options, $html5) {
            if ($html5 && 'single_text' === $options['widget'] && self::HTML5_FORMAT !== $options['format']) {
                throw new LogicException(\sprintf('Cannot use the "format" option of "%s" when the "html5" option is enabled.', self::class));
            }

            return $html5;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'date';
    }

    private function formatTimestamps(\IntlDateFormatter $formatter, string $regex, array $timestamps): array
    {
        $pattern = $formatter->getPattern();
        $timezone = $formatter->getTimeZoneId();
        $formattedTimestamps = [];

        $formatter->setTimeZone('UTC');

        if (preg_match($regex, $pattern, $matches)) {
            $formatter->setPattern($matches[0]);

            foreach ($timestamps as $timestamp => $choice) {
                $formattedTimestamps[$formatter->format($timestamp)] = $choice;
            }

            // I'd like to clone the formatter above, but then we get a
            // segmentation fault, so let's restore the old state instead
            $formatter->setPattern($pattern);
        }

        $formatter->setTimeZone($timezone);

        return $formattedTimestamps;
    }

    private function listYears(array $years): array
    {
        $result = [];

        foreach ($years as $year) {
            $result[\PHP_INT_SIZE === 4 ? \DateTimeImmutable::createFromFormat('Y e', $year.' UTC')->format('U') : gmmktime(0, 0, 0, 6, 15, $year)] = $year;
        }

        return $result;
    }

    private function listMonths(array $months): array
    {
        $result = [];

        foreach ($months as $month) {
            $result[gmmktime(0, 0, 0, $month, 15)] = $month;
        }

        return $result;
    }

    private function listDays(array $days): array
    {
        $result = [];

        foreach ($days as $day) {
            $result[gmmktime(0, 0, 0, 5, $day)] = $day;
        }

        return $result;
    }
}
