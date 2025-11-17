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
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CurrencyType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                if (!class_exists(Intl::class)) {
                    throw new LogicException(\sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
                }

                $choiceTranslationLocale = $options['choice_translation_locale'];
                $activeAt = $options['active_at'];
                $notActiveAt = $options['not_active_at'];
                $legalTender = $options['legal_tender'];
                $includeUndated = $options['include_undated'];

                if (null !== $activeAt && null !== $notActiveAt) {
                    throw new InvalidOptionsException('The "active_at" and "not_active_at" options cannot be used together.');
                }

                $legalTenderCacheKey = match ($legalTender) {
                    null => 'X',
                    true => '1',
                    false => '0',
                };

                return ChoiceList::loader(
                    $this,
                    new IntlCallbackChoiceLoader(
                        static function () use ($choiceTranslationLocale, $activeAt, $notActiveAt, $legalTender, $includeUndated) {
                            if (null === $activeAt && null === $notActiveAt && null === $legalTender) {
                                return array_flip(Currencies::getNames($choiceTranslationLocale));
                            }

                            $filteredCurrencyNames = [];

                            $active = match (true) {
                                null !== $activeAt => true,
                                null !== $notActiveAt => false,
                                default => null,
                            };

                            foreach (Currencies::getCurrencyCodes() as $code) {
                                if (!Currencies::isValidInAnyCountry($code, $legalTender, $active, $activeAt ?? $notActiveAt, $includeUndated)) {
                                    continue;
                                }

                                $filteredCurrencyNames[$code] = Currencies::getName($code, $choiceTranslationLocale);
                            }

                            return array_flip($filteredCurrencyNames);
                        },
                    ),
                    $choiceTranslationLocale.($activeAt ?? $notActiveAt)?->format('Y-m-d\TH:i:s').$legalTenderCacheKey.(int) $includeUndated,
                );
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'active_at' => new \DateTimeImmutable('today', new \DateTimeZone('Etc/UTC')),
            'not_active_at' => null,
            'include_undated' => true,
            'legal_tender' => true,
            'invalid_message' => 'Please select a valid currency.',
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
        $resolver->setAllowedTypes('active_at', [\DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('not_active_at', [\DateTimeInterface::class, 'null']);
        $resolver->setAllowedTypes('legal_tender', ['bool', 'null']);
        $resolver->setAllowedTypes('include_undated', 'bool');
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'currency';
    }
}
