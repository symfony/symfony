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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeZoneToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntlTimeZoneToStringTransformer;
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
            'choice_loader' => function (Options $options) {
                $regions = $options->offsetGet('regions', false);
                $input = $options['input'];

                return new CallbackChoiceLoader(function () use ($regions, $input) {
                    return self::getTimezones($regions, $input);
                });
            },
            'choice_translation_domain' => false,
            'input' => 'string',
            'regions' => \DateTimeZone::ALL,
        ]);

        $resolver->setAllowedValues('input', ['string', 'datetimezone', 'intltimezone']);
        $resolver->setNormalizer('input', function (Options $options, $value) {
            if ('intltimezone' === $value && !class_exists(\IntlTimeZone::class)) {
                throw new LogicException('Cannot use "intltimezone" input because the PHP intl extension is not available.');
            }

            return $value;
        });

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
    private static function getTimezones(int $regions, string $input): array
    {
        $timezones = [];

        foreach (\DateTimeZone::listIdentifiers($regions) as $timezone) {
            if ('intltimezone' === $input && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($timezone)->getID()) {
                continue;
            }

            $timezones[str_replace(['/', '_'], [' / ', ' '], $timezone)] = $timezone;
        }

        return $timezones;
    }
}
