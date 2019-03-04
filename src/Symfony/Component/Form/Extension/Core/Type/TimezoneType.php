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
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeZoneToStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
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
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                $regions = $options->offsetGet('regions', false);

                return new CallbackChoiceLoader(function () use ($regions) {
                    return self::getTimezones($regions);
                });
            },
            'choice_translation_domain' => false,
            'input' => 'string',
            'regions' => \DateTimeZone::ALL,
        ]);

        $resolver->setAllowedValues('input', ['string', 'datetimezone']);

        $resolver->setAllowedTypes('regions', 'int');
        $resolver->setDeprecated('regions', 'The option "%name%" is deprecated since Symfony 4.2.');
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return __NAMESPACE__.'\ChoiceType';
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'timezone';
    }

    /**
     * Returns a normalized array of timezone choices.
     */
    private static function getTimezones(int $regions): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers($regions) as $timezone) {
            $parts = explode('/', $timezone);

            if (\count($parts) > 2) {
                $region = $parts[0];
                $name = $parts[1].' - '.$parts[2];
            } elseif (\count($parts) > 1) {
                $region = $parts[0];
                $name = $parts[1];
            } else {
                $region = 'Other';
                $name = $parts[0];
            }

            $timezones[$region][str_replace('_', ' ', $name)] = $timezone;
        }

        return 1 === \count($timezones) ? reset($timezones) : $timezones;
    }
}
