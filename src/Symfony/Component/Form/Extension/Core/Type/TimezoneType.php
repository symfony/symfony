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
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeZoneToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntlTimeZoneToStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Timezones;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimezoneType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ('datetimezone' === $options['input']) {
            $builder->addModelTransformer(new DateTimeZoneToStringTransformer($options['multiple']));
        } elseif ('intltimezone' === $options['input']) {
            $builder->addModelTransformer(new IntlTimeZoneToStringTransformer($options['multiple']));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'intl' => false,
            'choice_loader' => function (Options $options) {
                $input = $options['input'];
                $grouping = $options['grouping'];

                if ($options['intl']) {
                    if (!class_exists(Intl::class)) {
                        throw new LogicException(sprintf('The "symfony/intl" component is required to use "%s" with option "intl=true". Try running "composer require symfony/intl".', static::class));
                    }

                    $choiceTranslationLocale = $options['choice_translation_locale'];

                    return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($input, $choiceTranslationLocale, $grouping) {
                        return self::getIntlTimezones($input, $choiceTranslationLocale, $grouping);
                    }), [$input, $choiceTranslationLocale]);
                }

                return ChoiceList::lazy($this, function () use ($input, $grouping) {
                    return self::getPhpTimezones($input, $grouping);
                }, $input);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'input' => 'string',
            'grouping' => false,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a valid timezone.';
            },
            'regions' => \DateTimeZone::ALL,
        ]);

        $resolver->setAllowedTypes('intl', ['bool']);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setNormalizer('choice_translation_locale', function (Options $options, $value) {
            if (null !== $value && !$options['intl']) {
                throw new LogicException('The "choice_translation_locale" option can only be used if the "intl" option is set to true.');
            }

            return $value;
        });

        $resolver->setAllowedValues('input', ['string', 'datetimezone', 'intltimezone']);
        $resolver->setNormalizer('input', function (Options $options, $value) {
            if ('intltimezone' === $value && !class_exists(\IntlTimeZone::class)) {
                throw new LogicException('Cannot use "intltimezone" input because the PHP intl extension is not available.');
            }

            return $value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'timezone';
    }

    private static function getPhpTimezones(string $input, bool $grouping = false): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $timezone) {
            if ('intltimezone' === $input && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                continue;
            }

            if ($grouping && dirname($timezone) != '.') {
                $timezones[str_replace(['/', '_'], [' / ', ' '], dirname($timezone))][str_replace('_', ' ', basename($timezone))] = $timezone;
            } else {
                $timezones[str_replace(['/', '_'], [' / ', ' '], $timezone)] = $timezone;
            }
        }

        return $timezones;
    }

    private static function getIntlTimezones(string $input, string $locale = null, bool $grouping = false): array
    {
        $timezones = array_flip(Timezones::getNames($locale));

        if ('intltimezone' === $input || $grouping) {
            foreach ($timezones as $name => $timezone) {
                if ('intltimezone' === $input && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                    unset($timezones[$name]);
                } elseif ($grouping && dirname($timezone) != '.') {
                    unset($timezones[$name]);
                    $timezones[str_replace(['/', '_'], [' / ', ' '], dirname($timezone))][$name] = $timezone;
                }
            }
        }

        return $timezones;
    }
}
