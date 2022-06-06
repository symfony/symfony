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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A choice type for native PHP enums.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class EnumType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['class'])
            ->setAllowedTypes('class', 'string')
            ->setAllowedValues('class', \Closure::fromCallable('enum_exists'))
            ->setDefault('choices', static function (Options $options): array {
                return $options['class']::cases();
            })
            ->setDefault('choice_label', static function (\UnitEnum $choice): string {
                return $choice->name;
            })
            ->setDefault('choice_value', static function (Options $options): ?\Closure {
                if (!is_a($options['class'], \BackedEnum::class, true)) {
                    return null;
                }

                return static function (?\BackedEnum $choice): ?string {
                    if (null === $choice) {
                        return null;
                    }

                    return (string) $choice->value;
                };
            })
        ;
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
