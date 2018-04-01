<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\Type;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symphony\Component\Form\Extension\Core\DataTransformer\DateTimeZoneToStringTransformer;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\OptionsResolver\Options;
use Symphony\Component\OptionsResolver\OptionsResolver;

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
        $resolver->setDefaults(array(
            'choice_loader' => function (Options $options) {
                $regions = $options['regions'];

                return new CallbackChoiceLoader(function () use ($regions) {
                    return self::getTimezones($regions);
                });
            },
            'choice_translation_domain' => false,
            'input' => 'string',
            'regions' => \DateTimeZone::ALL,
        ));

        $resolver->setAllowedValues('input', array('string', 'datetimezone'));

        $resolver->setAllowedTypes('regions', 'int');
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
        $timezones = array();

        foreach (\DateTimeZone::listIdentifiers($regions) as $timezone) {
            $parts = explode('/', $timezone);

            if (count($parts) > 2) {
                $region = $parts[0];
                $name = $parts[1].' - '.$parts[2];
            } elseif (count($parts) > 1) {
                $region = $parts[0];
                $name = $parts[1];
            } else {
                $region = 'Other';
                $name = $parts[0];
            }

            $timezones[$region][str_replace('_', ' ', $name)] = $timezone;
        }

        return 1 === count($timezones) ? reset($timezones) : $timezones;
    }
}
