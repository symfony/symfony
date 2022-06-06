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
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Languages;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LanguageType extends AbstractType
{
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
                $useAlpha3Codes = $options['alpha3'];
                $choiceSelfTranslation = $options['choice_self_translation'];

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale, $useAlpha3Codes, $choiceSelfTranslation) {
                    if (true === $choiceSelfTranslation) {
                        foreach (Languages::getLanguageCodes() as $alpha2Code) {
                            try {
                                $languageCode = $useAlpha3Codes ? Languages::getAlpha3Code($alpha2Code) : $alpha2Code;
                                $languagesList[$languageCode] = Languages::getName($alpha2Code, $alpha2Code);
                            } catch (MissingResourceException $e) {
                                // ignore errors like "Couldn't read the indices for the locale 'meta'"
                            }
                        }
                    } else {
                        $languagesList = $useAlpha3Codes ? Languages::getAlpha3Names($choiceTranslationLocale) : Languages::getNames($choiceTranslationLocale);
                    }

                    return array_flip($languagesList);
                }), [$choiceTranslationLocale, $useAlpha3Codes, $choiceSelfTranslation]);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'alpha3' => false,
            'choice_self_translation' => false,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a valid language.';
            },
        ]);

        $resolver->setAllowedTypes('choice_self_translation', ['bool']);
        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setAllowedTypes('alpha3', 'bool');

        $resolver->setNormalizer('choice_self_translation', function (Options $options, $value) {
            if (true === $value && $options['choice_translation_locale']) {
                throw new LogicException('Cannot use the "choice_self_translation" and "choice_translation_locale" options at the same time. Remove one of them.');
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
        return 'language';
    }
}
