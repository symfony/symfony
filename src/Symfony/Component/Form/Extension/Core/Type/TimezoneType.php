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
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
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
     * @return void
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
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'intl' => false,
            'choice_loader' => function (Options $options) {
                $input = $options['input'];

                if ($options['intl']) {
                    if (!class_exists(Intl::class)) {
                        throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s" with option "intl=true". Try running "composer require symfony/intl".', static::class));
                    }

                    $choiceTranslationLocale = $options['choice_translation_locale'];

                    return ChoiceList::loader($this, self::acceptEquivalentIdentifiers(
                        new IntlCallbackChoiceLoader(static fn () => self::getIntlTimezones($input, $choiceTranslationLocale)),
                        $input,
                    ), [$input, $choiceTranslationLocale]);
                }

                return ChoiceList::loader($this, self::acceptEquivalentIdentifiers(
                    new CallbackChoiceLoader(static fn () => self::getPhpTimezones($input)),
                    $input,
                ), $input);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'input' => 'string',
            'invalid_message' => 'Please select a valid timezone.',
            'regions' => \DateTimeZone::ALL,
        ]);

        $resolver->setAllowedTypes('intl', ['bool']);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setNormalizer('choice_translation_locale', static function (Options $options, $value) {
            if (null !== $value && !$options['intl']) {
                throw new LogicException('The "choice_translation_locale" option can only be used if the "intl" option is set to true.');
            }

            return $value;
        });

        $resolver->setAllowedValues('input', ['string', 'datetimezone', 'intltimezone']);
        $resolver->setNormalizer('input', static function (Options $options, $value) {
            if ('intltimezone' === $value && !class_exists(\IntlTimeZone::class)) {
                throw new LogicException('Cannot use "intltimezone" input because the PHP intl extension is not available.');
            }

            return $value;
        });
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'timezone';
    }

    private static function getPhpTimezones(string $input): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $timezone) {
            if ('intltimezone' === $input && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                continue;
            }

            $timezones[str_replace(['/', '_'], [' / ', ' '], $timezone)] = $timezone;
        }

        return $timezones;
    }

    private static function getIntlTimezones(string $input, ?string $locale = null): array
    {
        $timezones = array_flip(Timezones::getNames($locale));

        if ('intltimezone' === $input) {
            foreach ($timezones as $name => $timezone) {
                if ('Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                    unset($timezones[$name]);
                }
            }
        }

        return $timezones;
    }

    /**
     * A zone can be designated by several identifiers, of which the choice list can
     * offer only one: identifiers are unique but the labels they are keyed by are not.
     * The ones left out still designate an offered zone, so they must stay submittable.
     *
     * @return array<string, true>
     */
    private static function getSubmittableTimezones(string $input): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC) as $timezone) {
            if ('intltimezone' === $input && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                continue;
            }

            $timezones[$timezone] = true;
        }

        return $timezones;
    }

    private static function acceptEquivalentIdentifiers(ChoiceLoaderInterface $loader, string $input): ChoiceLoaderInterface
    {
        return new class($loader, static fn () => self::getSubmittableTimezones($input)) implements ChoiceLoaderInterface {
            private ?array $submittable = null;

            public function __construct(
                private ChoiceLoaderInterface $loader,
                private \Closure $timezones,
            ) {
            }

            public function loadChoiceList(?callable $value = null): ChoiceListInterface
            {
                return $this->loader->loadChoiceList($value);
            }

            public function loadChoicesForValues(array $values, ?callable $value = null): array
            {
                $choices = $this->loader->loadChoicesForValues($values, $value);
                $this->submittable ??= ($this->timezones)();

                foreach ($values as $i => $submitted) {
                    if (!isset($choices[$i]) && isset($this->submittable[$submitted])) {
                        $choices[$i] = $submitted;
                    }
                }

                return $choices;
            }

            public function loadValuesForChoices(array $choices, ?callable $value = null): array
            {
                return $this->loader->loadValuesForChoices($choices, $value);
            }
        };
    }
}
