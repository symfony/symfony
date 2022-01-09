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
                $withEmoji = $options['with_emoji'];
                $onlyEmoji = $options['only_emoji'];

                if ($onlyEmoji && $withEmoji) {
                    throw new LogicException(sprintf('Options "%s" and "%s" cannot be true together', 'with_emoji', 'only_emoji'));
                }

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale, $alpha3, $withEmoji, $onlyEmoji) {
                    $names = $alpha3 ? Countries::getAlpha3Names($choiceTranslationLocale) : Countries::getNames($choiceTranslationLocale);
                    $choices = $names;
                    if (true === $onlyEmoji) {
                        foreach ($names as $name => $displayed) {
                            $choices[$name] = self::getEmojiFlag($name);
                        }
                    } elseif (true === $withEmoji) {
                        foreach ($names as $name => $displayed) {
                            $choices[$name] = sprintf('%s - %s', self::getEmojiFlag($name), $displayed);
                        }
                    }

                    return array_flip($choices);
                }), [$choiceTranslationLocale, $alpha3]);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'alpha3' => false,
            'invalid_message' => 'Please select a valid country.',
            'with_emoji' => false,
            'only_emoji' => false,
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setAllowedTypes('alpha3', 'bool');
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

    private static function getEmojiFlag(string $name): string
    {
        return self::toRegionalSymbol($name, 0) . self::toRegionalSymbol($name, 1);
    }

    private static function toRegionalSymbol(string $name, int $position): string
    {
        $regionalOffset = 0x1F1A5;

        return mb_chr($regionalOffset + mb_ord(substr($name, $position, 1), 'UTF-8'), 'UTF-8');
    }
}
