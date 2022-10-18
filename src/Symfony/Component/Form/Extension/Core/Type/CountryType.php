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
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                if (!class_exists(Intl::class)) {
                    throw new LogicException(sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
                }

                $choiceTranslationLocale = $options['choice_translation_locale'];
                $alpha3 = $options['alpha3'];

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale, $alpha3) {
                    return array_flip($alpha3 ? Countries::getAlpha3Names($choiceTranslationLocale) : Countries::getNames($choiceTranslationLocale));
                }), [$choiceTranslationLocale, $alpha3]);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'alpha3' => false,
            'invalid_message' => 'Please select a valid country.',
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setAllowedTypes('alpha3', 'bool');
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'country';
    }
}
