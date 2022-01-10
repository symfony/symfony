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
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountryType extends AbstractType
{
    public const FORMAT_WITH_NAME = 'format_with_name';
    public const FORMAT_WITH_FLAG = 'format_with_flag';
    public const FORMAT_WITH_FLAG_AND_NAME = 'format_with_flag_and_name';

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                if (!class_exists(Intl::class)) {
                    throw new LogicException(sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
                }

                $choiceTranslationLocale = $options['choice_translation_locale'];
                $alpha3 = $options['alpha3'];
                $format = $options['format'];

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale, $alpha3, $format) {
                    $countriesCode = $alpha3 ? Countries::getAlpha3Names($choiceTranslationLocale) : Countries::getNames($choiceTranslationLocale);
                    $choices = $countriesCode;
                    if (self::FORMAT_WITH_FLAG === $format) {
                        foreach ($countriesCode as $countryCode => $displayed) {
                            $choices[$countryCode] = self::getEmojiFlag($countryCode);
                        }
                    } elseif (self::FORMAT_WITH_FLAG_AND_NAME === $format) {
                        $choices = [];
                        foreach ($countriesCode as $countryCode => $displayed) {
                            $choices[$countryCode] = sprintf('%s %s', self::getEmojiFlag($countryCode), $displayed);
                        }

                    }

                    return array_flip($choices);
                }), [$choiceTranslationLocale, $alpha3]);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'alpha3' => false,
            'invalid_message' => 'Please select a valid country.',
            'format' => self::FORMAT_WITH_NAME,
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setAllowedTypes('alpha3', 'bool');
        $resolver->setAllowedTypes('format', 'string');
        $resolver->setAllowedValues('format', [self::FORMAT_WITH_NAME, self::FORMAT_WITH_FLAG, self::FORMAT_WITH_FLAG_AND_NAME]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'country';
    }

    private static function getEmojiFlag(string $countryCode): string
    {
        $regionalOffset = 0x1F1A5;

        return mb_chr($regionalOffset + mb_ord($countryCode[0], 'UTF-8'), 'UTF-8')
            . mb_chr($regionalOffset + mb_ord($countryCode[1], 'UTF-8'), 'UTF-8');
    }
}
